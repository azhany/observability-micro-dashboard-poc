import { describe, it, expect, beforeEach, vi } from 'vitest';
import MetricService from '../MetricService';

global.fetch = vi.fn();

describe('MetricService', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('fetchHistoricalMetrics', () => {
        it('builds correct query parameters', async () => {
            const mockResponse = {
                resolution: '1m',
                count: 10,
                data: [],
            };

            (global.fetch as any).mockResolvedValueOnce({
                ok: true,
                json: async () => mockResponse,
            });

            await MetricService.fetchHistoricalMetrics('tenant-123', {
                resolution: '1m',
                metric_name: 'cpu_usage',
                start_time: '2026-01-20T10:00:00Z',
                end_time: '2026-01-20T11:00:00Z',
            });

            expect(global.fetch).toHaveBeenCalledWith(
                expect.stringContaining('resolution=1m'),
                expect.objectContaining({
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Tenant-ID': 'tenant-123',
                    },
                })
            );

            const callUrl = (global.fetch as any).mock.calls[0][0];
            expect(callUrl).toContain('metric_name=cpu_usage');
            expect(callUrl).toContain('start_time=2026-01-20T10%3A00%3A00Z');
            expect(callUrl).toContain('end_time=2026-01-20T11%3A00%3A00Z');
        });

        it('returns parsed JSON response', async () => {
            const mockResponse = {
                resolution: '5m',
                count: 5,
                data: [
                    { metric_name: 'memory_usage', value: 75.5, timestamp: '2026-01-20T10:00:00Z' },
                ],
            };

            (global.fetch as any).mockResolvedValueOnce({
                ok: true,
                json: async () => mockResponse,
            });

            const result = await MetricService.fetchHistoricalMetrics('tenant-456', {
                resolution: '5m',
            });

            expect(result).toEqual(mockResponse);
        });

        it('throws error when fetch fails', async () => {
            (global.fetch as any).mockResolvedValueOnce({
                ok: false,
                statusText: 'Not Found',
            });

            await expect(
                MetricService.fetchHistoricalMetrics('tenant-789', {})
            ).rejects.toThrow('Failed to fetch metrics: Not Found');
        });

        it('filters by agent_id client-side when provided', async () => {
            const mockResponse = {
                resolution: 'raw',
                count: 3,
                data: [
                    { metric_name: 'cpu_usage', value: 50, timestamp: '2026-01-20T10:00:00Z', agent_id: 'agent-1' },
                    { metric_name: 'cpu_usage', value: 60, timestamp: '2026-01-20T10:01:00Z', agent_id: 'agent-2' },
                    { metric_name: 'cpu_usage', value: 70, timestamp: '2026-01-20T10:02:00Z', agent_id: 'agent-1' },
                ],
            };

            (global.fetch as any).mockResolvedValueOnce({
                ok: true,
                json: async () => mockResponse,
            });

            const result = await MetricService.fetchHistoricalMetrics('tenant-123', {
                agent_id: 'agent-1',
            });

            expect(result.count).toBe(2);
            expect(result.data).toHaveLength(2);
            expect(result.data.every((d: any) => d.agent_id === 'agent-1')).toBe(true);
        });
    });

    describe('getTimeRangeForHours', () => {
        it('calculates correct time range for given hours', () => {
            const hours = 24;
            const result = MetricService.getTimeRangeForHours(hours);

            const now = new Date();
            const expectedStart = new Date(now.getTime() - hours * 60 * 60 * 1000);

            // Check that times are close (within 1 second to account for test execution time)
            const startTime = new Date(result.start_time);
            const endTime = new Date(result.end_time);

            expect(Math.abs(startTime.getTime() - expectedStart.getTime())).toBeLessThan(1000);
            expect(Math.abs(endTime.getTime() - now.getTime())).toBeLessThan(1000);
        });

        it('returns ISO format timestamps', () => {
            const result = MetricService.getTimeRangeForHours(1);

            expect(result.start_time).toMatch(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/);
            expect(result.end_time).toMatch(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/);
        });

        it('handles various hour values correctly', () => {
            const testCases = [1, 24, 168]; // 1h, 24h, 7d

            testCases.forEach(hours => {
                const result = MetricService.getTimeRangeForHours(hours);
                const start = new Date(result.start_time);
                const end = new Date(result.end_time);
                const diffHours = (end.getTime() - start.getTime()) / (1000 * 60 * 60);

                expect(Math.abs(diffHours - hours)).toBeLessThan(0.001); // Within 3.6 seconds
            });
        });
    });
});
