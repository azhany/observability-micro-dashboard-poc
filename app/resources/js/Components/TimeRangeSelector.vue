<script setup lang="ts">
import { ref, watch } from 'vue';

export interface TimeRange {
    label: string;
    value: string;
    hours: number;
    resolution: 'raw' | '1m' | '5m';
}

const props = defineProps<{
    modelValue: string;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
    (e: 'change', range: TimeRange): void;
}>();

const timeRanges: TimeRange[] = [
    { label: 'Live', value: 'live', hours: 0, resolution: 'raw' },
    { label: '1 Hour', value: '1h', hours: 1, resolution: '1m' },
    { label: '24 Hours', value: '24h', hours: 24, resolution: '1m' },
    { label: '7 Days', value: '7d', hours: 168, resolution: '5m' },
];

const selectedRange = ref(props.modelValue || 'live');

watch(selectedRange, (newValue) => {
    emit('update:modelValue', newValue);
    const range = timeRanges.find(r => r.value === newValue);
    if (range) {
        emit('change', range);
    }
});

watch(() => props.modelValue, (newValue) => {
    selectedRange.value = newValue;
});
</script>

<template>
    <div class="flex items-center gap-2 bg-gray-100 dark:bg-gray-700 rounded-lg p-1">
        <button
            v-for="range in timeRanges"
            :key="range.value"
            @click="selectedRange = range.value"
            class="px-3 py-1.5 text-sm font-medium rounded-md transition-all duration-200"
            :class="{
                'bg-white dark:bg-gray-800 text-blue-600 dark:text-blue-400 shadow-sm': selectedRange === range.value,
                'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white': selectedRange !== range.value
            }"
        >
            {{ range.label }}
        </button>
    </div>
</template>
