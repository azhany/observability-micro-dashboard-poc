<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import Chart, { type ChartItem } from 'chart.js/auto';
import StreamService from '../Services/StreamService';

const props = defineProps<{
    tenantId: string;
    metricName: string;
    label: string;
    color?: string;
    maxPoints?: number;
    agentId?: string;
}>();

const chartCanvas = ref<HTMLCanvasElement | null>(null);
let chartInstance: Chart | null = null;
const isLive = ref(false);
const hasError = ref(false);

const MAX_POINTS = props.maxPoints || 50;
const STATUS_TIMEOUT = 5000; // 5 seconds without data = not live
let lastDataTime = 0;
let statusCheckInterval: number | null = null;

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

onMounted(() => {
    initChart();
    
    // Connect to stream service
    StreamService.connect(props.tenantId);
    isLive.value = StreamService.isConnected();
    
    // Listen to events
    StreamService.on('message', handleData);
    StreamService.on('connected', handleConnection);
    StreamService.on('error', handleError);

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
        <div class="absolute top-4 right-4 flex items-center gap-2 z-10">
            <span class="text-xs text-gray-500 dark:text-gray-400 font-medium">
                {{ hasError ? 'OFFLINE' : (isLive ? 'LIVE' : 'CONNECTING') }}
            </span>
            <div 
                class="w-2 h-2 rounded-full transition-all duration-500" 
                :class="{ 
                    'bg-red-500 animate-pulse': isLive && !hasError,
                    'bg-gray-400': !isLive && !hasError,
                    'bg-red-800': hasError
                }"
            ></div>
        </div>
        <div class="h-full w-full">
            <canvas ref="chartCanvas"></canvas>
        </div>
    </div>
</template>
