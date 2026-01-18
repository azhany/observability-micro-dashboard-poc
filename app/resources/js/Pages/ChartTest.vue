<script setup lang="ts">
import RealtimeChart from '../Components/RealtimeChart.vue';
</script>

<template>
    <div class="min-h-screen bg-gray-50 flex flex-col items-center justify-center p-8">
        <h1 class="text-3xl font-bold mb-8 text-gray-800">Realtime Chart Verification</h1>
        
        <div class="w-full max-w-4xl grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- CPU Chart -->
            <div class="bg-white p-4 rounded-xl shadow-sm">
                <h2 class="text-lg font-semibold mb-4 text-gray-700">CPU Usage</h2>
                <RealtimeChart 
                    tenantId="tenant-demo" 
                    metricName="cpu_usage" 
                    label="CPU %" 
                    color="rgb(255, 99, 132)" 
                />
            </div>

            <!-- Memory Chart -->
            <div class="bg-white p-4 rounded-xl shadow-sm">
                <h2 class="text-lg font-semibold mb-4 text-gray-700">Memory Usage</h2>
                <RealtimeChart 
                    tenantId="tenant-demo" 
                    metricName="memory_usage" 
                    label="Memory (MB)" 
                    color="rgb(54, 162, 235)" 
                />
            </div>
        </div>

        <div class="mt-8 text-gray-500 text-sm">
            <p>Note: To verify updates, a backend stream is required or 'StreamService' must be mocked.</p>
        </div>
    </div>
</template>
