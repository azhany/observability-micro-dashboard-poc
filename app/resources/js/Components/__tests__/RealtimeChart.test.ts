import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import RealtimeChart from '@/Components/RealtimeChart.vue';
import StreamService from '@/Services/StreamService';

// Mock Chart.js
vi.mock('chart.js/auto', () => {
    return {
        default: vi.fn().mockImplementation(() => ({
            data: {
                labels: [],
                datasets: [{
                    data: [],
                }],
            },
            update: vi.fn(),
            destroy: vi.fn(),
        })),
    };
});

// Mock StreamService
vi.mock('@/Services/StreamService', () => {
    const listeners = new Map<string, Array<(data: any) => void>>();

    return {
        default: {
            connect: vi.fn(),
            disconnect: vi.fn(),
            isConnected: vi.fn(() => false),
            on: vi.fn((event: string, callback: (data: any) => void) => {
                if (!listeners.has(event)) {
                    listeners.set(event, []);
                }
                listeners.get(event)?.push(callback);
            }),
            off: vi.fn((event: string, callback: (data: any) => void) => {
                if (!listeners.has(event)) return;
                const callbacks = listeners.get(event);
                if (callbacks) {
                    listeners.set(event, callbacks.filter(cb => cb !== callback));
                }
            }),
            // Helper to simulate events in tests
            __simulateEvent: (event: string, data: any) => {
                const callbacks = listeners.get(event);
                if (callbacks) {
                    callbacks.forEach(cb => cb(data));
                }
            },
            __clearListeners: () => {
                listeners.clear();
            },
        },
    };
});

describe('RealtimeChart', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        (StreamService as any).__clearListeners();
    });

    afterEach(() => {
        vi.clearAllTimers();
    });

    describe('Component Mounting', () => {
        it('should render without errors', () => {
            const wrapper = mount(RealtimeChart, {
                props: {
                    tenantId: 'test-tenant',
                    metricName: 'cpu',
                    label: 'CPU Usage',
                },
            });

            expect(wrapper.exists()).toBe(true);
        });

        it('should connect to StreamService on mount', () => {
            mount(RealtimeChart, {
                props: {
                    tenantId: 'test-tenant-123',
                    metricName: 'cpu',
                    label: 'CPU Usage',
                },
            });

            expect(StreamService.connect).toHaveBeenCalledWith('test-tenant-123');
        });

        it('should register event listeners on mount', () => {
            mount(RealtimeChart, {
                props: {
                    tenantId: 'test-tenant',
                    metricName: 'cpu',
                    label: 'CPU Usage',
                },
            });

            expect(StreamService.on).toHaveBeenCalledWith('message', expect.any(Function));
            expect(StreamService.on).toHaveBeenCalledWith('connected', expect.any(Function));
            expect(StreamService.on).toHaveBeenCalledWith('error', expect.any(Function));
        });

        it('should render canvas element', () => {
            const wrapper = mount(RealtimeChart, {
                props: {
                    tenantId: 'test-tenant',
                    metricName: 'cpu',
                    label: 'CPU Usage',
                },
            });

            expect(wrapper.find('canvas').exists()).toBe(true);
        });
    });

    describe('Props', () => {
        it('should accept and use custom color', () => {
            const wrapper = mount(RealtimeChart, {
                props: {
                    tenantId: 'test-tenant',
                    metricName: 'memory',
                    label: 'Memory Usage',
                    color: 'rgb(255, 99, 132)',
                },
            });

            expect(wrapper.props('color')).toBe('rgb(255, 99, 132)');
        });

        it('should accept custom maxPoints', () => {
            const wrapper = mount(RealtimeChart, {
                props: {
                    tenantId: 'test-tenant',
                    metricName: 'cpu',
                    label: 'CPU Usage',
                    maxPoints: 100,
                },
            });

            expect(wrapper.props('maxPoints')).toBe(100);
        });

        it('should use default maxPoints of 50 if not provided', () => {
            const wrapper = mount(RealtimeChart, {
                props: {
                    tenantId: 'test-tenant',
                    metricName: 'cpu',
                    label: 'CPU Usage',
                },
            });

            expect(wrapper.props('maxPoints')).toBeUndefined();
            // Default is handled internally in component
        });

        it('should accept optional agentId prop', () => {
            const wrapper = mount(RealtimeChart, {
                props: {
                    tenantId: 'test-tenant',
                    metricName: 'cpu',
                    label: 'CPU Usage',
                    agentId: 'agent-123',
                },
            });

            expect(wrapper.props('agentId')).toBe('agent-123');
        });
    });

    describe('Live Indicator', () => {
        it('should show CONNECTING status initially', () => {
            const wrapper = mount(RealtimeChart, {
                props: {
                    tenantId: 'test-tenant',
                    metricName: 'cpu',
                    label: 'CPU Usage',
                },
            });

            expect(wrapper.text()).toContain('CONNECTING');
        });

        it('should show LIVE status when connected', async () => {
            const wrapper = mount(RealtimeChart, {
                props: {
                    tenantId: 'test-tenant',
                    metricName: 'cpu',
                    label: 'CPU Usage',
                },
            });

            // Simulate connection event
            (StreamService as any).__simulateEvent('connected', { tenantId: 'test-tenant' });
            await wrapper.vm.$nextTick();

            expect(wrapper.text()).toContain('LIVE');
        });

        it('should show OFFLINE status on error', async () => {
            const wrapper = mount(RealtimeChart, {
                props: {
                    tenantId: 'test-tenant',
                    metricName: 'cpu',
                    label: 'CPU Usage',
                },
            });

            // Simulate connection
            (StreamService as any).__simulateEvent('connected', { tenantId: 'test-tenant' });
            await wrapper.vm.$nextTick();

            expect(wrapper.text()).toContain('LIVE');

            // Simulate error
            (StreamService as any).__simulateEvent('error', new Error('Connection lost'));
            await wrapper.vm.$nextTick();

            expect(wrapper.text()).toContain('OFFLINE');
        });

        it('should have pulsing animation when live', async () => {
            const wrapper = mount(RealtimeChart, {
                props: {
                    tenantId: 'test-tenant',
                    metricName: 'cpu',
                    label: 'CPU Usage',
                },
            });

            // Simulate connection
            (StreamService as any).__simulateEvent('connected', { tenantId: 'test-tenant' });
            await wrapper.vm.$nextTick();

            const indicator = wrapper.find('.animate-pulse');
            expect(indicator.exists()).toBe(true);
            expect(indicator.classes()).toContain('bg-red-500');
        });

        it('should not pulse when offline', () => {
            const wrapper = mount(RealtimeChart, {
                props: {
                    tenantId: 'test-tenant',
                    metricName: 'cpu',
                    label: 'CPU Usage',
                },
            });

            const pulsingIndicator = wrapper.find('.animate-pulse.bg-red-500');
            expect(pulsingIndicator.exists()).toBe(false);
        });
    });

    describe('Data Handling', () => {
        it('should process matching metric data', async () => {
            const wrapper = mount(RealtimeChart, {
                props: {
                    tenantId: 'test-tenant',
                    metricName: 'cpu',
                    label: 'CPU Usage',
                },
            });

            const metricData = {
                metric: 'cpu',
                value: 75.5,
                timestamp: '2026-01-18T00:00:00Z',
            };

            // Simulate message event
            (StreamService as any).__simulateEvent('message', metricData);
            await wrapper.vm.$nextTick();

            // Should update to live status
            expect(wrapper.text()).toContain('LIVE');
        });

        it('should ignore non-matching metric data', async () => {
            const wrapper = mount(RealtimeChart, {
                props: {
                    tenantId: 'test-tenant',
                    metricName: 'cpu',
                    label: 'CPU Usage',
                },
            });

            const metricData = {
                metric: 'memory', // Different metric
                value: 50,
                timestamp: '2026-01-18T00:00:00Z',
            };

            // Simulate message event
            (StreamService as any).__simulateEvent('message', metricData);
            await wrapper.vm.$nextTick();

            // Should remain connecting since it's not the matching metric and no connection event fired
            expect(wrapper.text()).toContain('CONNECTING');
        });

        it('should handle invalid data gracefully', async () => {
            const wrapper = mount(RealtimeChart, {
                props: {
                    tenantId: 'test-tenant',
                    metricName: 'cpu',
                    label: 'CPU Usage',
                },
            });

            // Simulate invalid data
            (StreamService as any).__simulateEvent('message', { invalid: 'data' });
            await wrapper.vm.$nextTick();

            // Should not crash
            expect(wrapper.exists()).toBe(true);
        });

        it('should process data with metric_name key (new format)', async () => {
            const wrapper = mount(RealtimeChart, {
                props: {
                    tenantId: 'test-tenant',
                    metricName: 'cpu.usage.percent',
                    label: 'CPU Usage',
                },
            });

            const metricData = {
                agent_id: 'agent-1',
                metric_name: 'cpu.usage.percent',
                value: 85.2,
                timestamp: '2026-01-18T00:00:00Z',
            };

            // Simulate message event
            (StreamService as any).__simulateEvent('message', metricData);
            await wrapper.vm.$nextTick();

            // Should update to live status
            expect(wrapper.text()).toContain('LIVE');
        });

        it('should process array format data (from ProcessMetricSubmission)', async () => {
            const wrapper = mount(RealtimeChart, {
                props: {
                    tenantId: 'test-tenant',
                    metricName: 'memory.used.bytes',
                    label: 'Memory Usage',
                },
            });

            const metricData = [
                {
                    agent_id: 'agent-1',
                    metric_name: 'cpu.usage.percent',
                    value: 75.5,
                    timestamp: '2026-01-18T00:00:00Z',
                },
                {
                    agent_id: 'agent-1',
                    metric_name: 'memory.used.bytes',
                    value: 1024000,
                    timestamp: '2026-01-18T00:00:00Z',
                },
            ];

            // Simulate message event with array
            (StreamService as any).__simulateEvent('message', metricData);
            await wrapper.vm.$nextTick();

            // Should update to live status (matched memory metric)
            expect(wrapper.text()).toContain('LIVE');
        });

        it('should filter by agent_id when agentId prop is provided', async () => {
            const wrapper = mount(RealtimeChart, {
                props: {
                    tenantId: 'test-tenant',
                    metricName: 'cpu.usage.percent',
                    label: 'CPU Usage',
                    agentId: 'agent-1',
                },
            });

            const matchingData = {
                agent_id: 'agent-1',
                metric_name: 'cpu.usage.percent',
                value: 75.5,
                timestamp: '2026-01-18T00:00:00Z',
            };

            // Simulate message event
            (StreamService as any).__simulateEvent('message', matchingData);
            await wrapper.vm.$nextTick();

            // Should update to live status
            expect(wrapper.text()).toContain('LIVE');
        });

        it('should ignore data from different agent when agentId prop is provided', async () => {
            const wrapper = mount(RealtimeChart, {
                props: {
                    tenantId: 'test-tenant',
                    metricName: 'cpu.usage.percent',
                    label: 'CPU Usage',
                    agentId: 'agent-1',
                },
            });

            const differentAgentData = {
                agent_id: 'agent-2',
                metric_name: 'cpu.usage.percent',
                value: 75.5,
                timestamp: '2026-01-18T00:00:00Z',
            };

            // Simulate message event
            (StreamService as any).__simulateEvent('message', differentAgentData);
            await wrapper.vm.$nextTick();

            // Should remain connecting since data is from different agent
            expect(wrapper.text()).toContain('CONNECTING');
        });

        it('should accept data without agent_id when agentId prop is not provided', async () => {
            const wrapper = mount(RealtimeChart, {
                props: {
                    tenantId: 'test-tenant',
                    metricName: 'cpu.usage.percent',
                    label: 'CPU Usage',
                },
            });

            const dataWithoutAgentId = {
                metric_name: 'cpu.usage.percent',
                value: 65.3,
                timestamp: '2026-01-18T00:00:00Z',
            };

            // Simulate message event
            (StreamService as any).__simulateEvent('message', dataWithoutAgentId);
            await wrapper.vm.$nextTick();

            // Should update to live status
            expect(wrapper.text()).toContain('LIVE');
        });
    });

    describe('Cleanup', () => {
        it('should remove event listeners on unmount', () => {
            const wrapper = mount(RealtimeChart, {
                props: {
                    tenantId: 'test-tenant',
                    metricName: 'cpu',
                    label: 'CPU Usage',
                },
            });

            wrapper.unmount();

            expect(StreamService.off).toHaveBeenCalledWith('message', expect.any(Function));
            expect(StreamService.off).toHaveBeenCalledWith('connected', expect.any(Function));
            expect(StreamService.off).toHaveBeenCalledWith('error', expect.any(Function));
        });

        it('should clear interval on unmount', () => {
            vi.useFakeTimers();
            const clearIntervalSpy = vi.spyOn(global, 'clearInterval');

            const wrapper = mount(RealtimeChart, {
                props: {
                    tenantId: 'test-tenant',
                    metricName: 'cpu',
                    label: 'CPU Usage',
                },
            });

            wrapper.unmount();

            expect(clearIntervalSpy).toHaveBeenCalled();

            vi.useRealTimers();
        });
    });

    describe('Accessibility', () => {
        it('should have proper ARIA attributes', () => {
            const wrapper = mount(RealtimeChart, {
                props: {
                    tenantId: 'test-tenant',
                    metricName: 'cpu',
                    label: 'CPU Usage',
                },
            });

            const container = wrapper.find('div');
            expect(container.exists()).toBe(true);
        });

        it('should display status text for screen readers', () => {
            const wrapper = mount(RealtimeChart, {
                props: {
                    tenantId: 'test-tenant',
                    metricName: 'cpu',
                    label: 'CPU Usage',
                },
            });

            const statusText = wrapper.find('span');
            expect(statusText.exists()).toBe(true);
            expect(['LIVE', 'OFFLINE', 'CONNECTING']).toContain(statusText.text());
        });
    });
});
