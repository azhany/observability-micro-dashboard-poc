<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import Chart, { type ChartItem } from 'chart.js/auto';
import annotationPlugin from 'chartjs-plugin-annotation';
import StreamService from '../Services/StreamService';
import MetricService, { type AlertDataPoint } from '../Services/MetricService';
import TimeRangeSelector, { type TimeRange } from './TimeRangeSelector.vue';

// Register annotation plugin
Chart.register(annotationPlugin);

const props = defineProps<{
    tenantId: string;
    metricName: string;
    label: string;
    color?: string;
    maxPoints?: number;
    agentId?: string;
    showTimeRangeSelector?: boolean;
}>();

const chartCanvas = ref<HTMLCanvasElement | null>(null);
let chartInstance: Chart | null = null;
const isLive = ref(false);
const hasError = ref(false);
const isHistoricalMode = ref(false);
const isLoading = ref(false);
const selectedTimeRange = ref('live');

const MAX_POINTS = props.maxPoints || 50;
const STATUS_TIMEOUT = 5000; // 5 seconds without data = not live
let lastDataTime = 0;
let statusCheckInterval: number | null = null;
let sseListenersActive = false;

const initChart = () => {
    if (!chartCanvas.value) return;

    chartInstance = new Chart(chartCanvas.value as ChartItem, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: props.label,
                data: [],
                borderColor: props.color || 'rgb(75, 192, 192)',
                tension: 0.1,
                fill: false,
                pointRadius: 0, // Hide points for cleaner realtime look
                borderWidth: 2
            }]
        },
        options: {
             responsive: true,
             maintainAspectRatio: false,
             animation: false, // Important for performance
             interaction: {
                intersect: false,
                mode: 'index',
             },
             plugins: {
                legend: {
                    display: true,
                    labels: {
                        color: 'gray' // Adapt to theme if needed
                    }
                },
                annotation: {
                    annotations: {}
                }
             },
             scales: {
                x: {
                    display: true,
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: 'gray',
                        maxRotation: 0,
                        autoSkip: true,
                        maxTicksLimit: 10
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(200, 200, 200, 0.1)'
                    },
                    ticks: {
                        color: 'gray'
                    }
                }
             }
        }
    });
};

const handleData = (data: any) => {
    // Handle both array format (from ProcessMetricSubmission) and single object format
    const metrics = Array.isArray(data) ? data : [data];

    for (const item of metrics) {
        // Support both 'metric_name' (new format) and 'metric' (legacy format)
        const metricName = item.metric_name || item.metric;

        if (!item || !metricName || item.value === undefined) continue;

        // Check if data matches our metric
        if (metricName !== props.metricName) continue;

        // Filter by agent_id if agentId prop is provided
        if (props.agentId && item.agent_id !== props.agentId) continue;

        if (!chartInstance) continue;

        const timestamp = new Date().toLocaleTimeString();
        const value = Number(item.value);

        // Add Data
        chartInstance.data.labels?.push(timestamp);
        chartInstance.data.datasets[0].data.push(value);

        // Remove old data to prevent memory leaks
        if (chartInstance.data.labels!.length > MAX_POINTS) {
            chartInstance.data.labels!.shift();
            chartInstance.data.datasets[0].data.shift();
        }

        // Optimized update
        chartInstance.update('none');

        lastDataTime = Date.now();
        isLive.value = true;
        hasError.value = false;
    }
};

const handleConnection = () => {
    isLive.value = true;
    hasError.value = false;
};

const handleError = () => {
    isLive.value = false;
    hasError.value = true;
};

const updateAlertAnnotations = (alerts: AlertDataPoint[]) => {
    if (!chartInstance) return;

    const annotations: any = {};

    alerts.forEach((alert, index) => {
        if (alert.state === 'FIRING' || alert.state === 'PENDING') {
            const timestamp = new Date(alert.started_at).toLocaleString();

            annotations[`alert-${index}`] = {
                type: 'line',
                xMin: timestamp,
                xMax: timestamp,
                borderColor: alert.state === 'FIRING' ? 'rgba(239, 68, 68, 0.8)' : 'rgba(251, 191, 36, 0.8)', // red-500 or amber-400
                borderWidth: 2,
                borderDash: [5, 5],
                label: {
                    content: `${alert.state}`,
                    enabled: true,
                    position: 'start',
                    backgroundColor: alert.state === 'FIRING' ? 'rgba(239, 68, 68, 0.9)' : 'rgba(251, 191, 36, 0.9)',
                    color: 'white',
                    font: {
                        size: 10
                    }
                }
            };
        }
    });

    // Update chart options with new annotations
    if (chartInstance.options.plugins?.annotation) {
        chartInstance.options.plugins.annotation.annotations = annotations;
        chartInstance.update();
    }
};

const loadHistoricalData = async (timeRange: TimeRange) => {
    if (!chartInstance || timeRange.value === 'live') return;

    try {
        isLoading.value = true;
        isHistoricalMode.value = true;
        hasError.value = false;

        // Pause SSE updates
        pauseSSEUpdates();

        // Calculate time range
        const { start_time, end_time } = MetricService.getTimeRangeForHours(timeRange.hours);

        // Fetch historical data and alerts in parallel
        const [metricsResponse, alertsResponse] = await Promise.all([
            MetricService.fetchHistoricalMetrics(props.tenantId, {
                resolution: timeRange.resolution,
                metric_name: props.metricName,
                start_time,
                end_time,
                agent_id: props.agentId,
            }),
            MetricService.fetchAlerts(props.tenantId, {
                metric_name: props.metricName,
                start_time,
                end_time,
            }).catch(() => ({ count: 0, data: [] })) // Gracefully handle alert fetch errors
        ]);

        // Clear existing data
        chartInstance.data.labels = [];
        chartInstance.data.datasets[0].data = [];

        // Populate chart with historical data (reverse to show oldest first)
        const sortedData = metricsResponse.data.reverse();
        for (const point of sortedData) {
            const timestamp = new Date(point.timestamp).toLocaleString();
            chartInstance.data.labels?.push(timestamp);
            chartInstance.data.datasets[0].data.push(point.value);
        }

        // Update alert annotations
        updateAlertAnnotations(alertsResponse.data);

        chartInstance.update();
        isLoading.value = false;
    } catch (error) {
        console.error('Failed to load historical data:', error);
        hasError.value = true;
        isLoading.value = false;
    }
};

const pauseSSEUpdates = () => {
    if (sseListenersActive) {
        StreamService.off('message', handleData);
        StreamService.off('connected', handleConnection);
        StreamService.off('error', handleError);
        sseListenersActive = false;
    }
};

const resumeSSEUpdates = () => {
    if (!sseListenersActive) {
        // Clear historical data and annotations
        if (chartInstance) {
            chartInstance.data.labels = [];
            chartInstance.data.datasets[0].data = [];

            // Clear alert annotations
            if (chartInstance.options.plugins?.annotation) {
                chartInstance.options.plugins.annotation.annotations = {};
            }

            chartInstance.update();
        }

        StreamService.on('message', handleData);
        StreamService.on('connected', handleConnection);
        StreamService.on('error', handleError);
        sseListenersActive = true;
        isLive.value = StreamService.isConnected();
    }
};

const handleTimeRangeChange = (range: TimeRange) => {
    if (range.value === 'live') {
        isHistoricalMode.value = false;
        resumeSSEUpdates();
    } else {
        loadHistoricalData(range);
    }
};

onMounted(() => {
    initChart();

    // Connect to stream service
    StreamService.connect(props.tenantId);
    isLive.value = StreamService.isConnected();

    // Listen to events
    StreamService.on('message', handleData);
    StreamService.on('connected', handleConnection);
    StreamService.on('error', handleError);
    sseListenersActive = true;

    // Watchdog for live status
    statusCheckInterval = window.setInterval(() => {
        if (isLive.value && Date.now() - lastDataTime > STATUS_TIMEOUT) {
            // No data for a while, but connection might still be open
            // We'll keep isLive based on service status but maybe dim the pulse
        }
    }, 2000);
});

onUnmounted(() => {
    StreamService.off('message', handleData);
    StreamService.off('connected', handleConnection);
    StreamService.off('error', handleError);
    
    if (statusCheckInterval) clearInterval(statusCheckInterval);
    
    if (chartInstance) {
        chartInstance.destroy();
    }
});
</script>

<template>
    <div class="relative w-full h-64 bg-white dark:bg-gray-800 rounded-lg shadow p-4 border border-gray-200 dark:border-gray-700">
        <!-- Time Range Selector (optional) -->
        <div v-if="showTimeRangeSelector" class="absolute top-4 left-4 z-10">
            <TimeRangeSelector
                v-model="selectedTimeRange"
                @change="handleTimeRangeChange"
            />
        </div>

        <!-- Status Indicator -->
        <div class="absolute top-4 right-4 flex items-center gap-2 z-10">
            <span class="text-xs text-gray-500 dark:text-gray-400 font-medium">
                {{
                    isLoading ? 'LOADING' :
                    hasError ? 'ERROR' :
                    isHistoricalMode ? 'HISTORICAL' :
                    (isLive ? 'LIVE' : 'CONNECTING')
                }}
            </span>
            <div
                class="w-2 h-2 rounded-full transition-all duration-500"
                :class="{
                    'bg-red-500 animate-pulse': isLive && !hasError && !isHistoricalMode,
                    'bg-blue-500': isHistoricalMode && !hasError && !isLoading,
                    'bg-gray-400 animate-pulse': isLoading,
                    'bg-gray-400': !isLive && !hasError && !isHistoricalMode,
                    'bg-red-800': hasError
                }"
            ></div>
        </div>

        <!-- Loading Overlay -->
        <div
            v-if="isLoading"
            class="absolute inset-0 bg-white/50 dark:bg-gray-800/50 backdrop-blur-sm rounded-lg flex items-center justify-center z-20"
        >
            <div class="flex items-center gap-2">
                <div class="w-4 h-4 border-2 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
                <span class="text-sm text-gray-600 dark:text-gray-300">Loading data...</span>
            </div>
        </div>

        <div class="h-full w-full">
            <canvas ref="chartCanvas"></canvas>
        </div>
    </div>
</template>
