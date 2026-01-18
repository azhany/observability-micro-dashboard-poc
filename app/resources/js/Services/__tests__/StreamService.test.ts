import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import StreamService from '@/Services/StreamService';

// Mock EventSource
class MockEventSource {
    public url: string;
    public readyState: number = 0; // CONNECTING
    public onopen: ((event: Event) => void) | null = null;
    public onmessage: ((event: MessageEvent) => void) | null = null;
    public onerror: ((event: Event) => void) | null = null;

    static CONNECTING = 0;
    static OPEN = 1;
    static CLOSED = 2;

    constructor(url: string) {
        this.url = url;
        // Simulate async connection
        setTimeout(() => {
            this.readyState = MockEventSource.OPEN;
            if (this.onopen) {
                this.onopen(new Event('open'));
            }
        }, 0);
    }

    close() {
        this.readyState = MockEventSource.CLOSED;
    }

    // Helper for testing
    simulateMessage(data: string) {
        if (this.onmessage) {
            const event = new MessageEvent('message', { data });
            this.onmessage(event);
        }
    }

    simulateError() {
        if (this.onerror) {
            this.onerror(new Event('error'));
        }
    }
}

// Replace global EventSource with mock
global.EventSource = MockEventSource as any;

describe('StreamService', () => {
    let consoleLogSpy: any;
    let consoleErrorSpy: any;

    beforeEach(() => {
        // Reset service state by disconnecting
        StreamService.disconnect();

        // Spy on console methods
        consoleLogSpy = vi.spyOn(console, 'log').mockImplementation(() => { });
        consoleErrorSpy = vi.spyOn(console, 'error').mockImplementation(() => { });
    });

    afterEach(() => {
        StreamService.disconnect();
        consoleLogSpy.mockRestore();
        consoleErrorSpy.mockRestore();
    });

    describe('Singleton Pattern', () => {
        it('should return the same instance', () => {
            const instance1 = StreamService;
            const instance2 = StreamService;
            expect(instance1).toBe(instance2);
        });
    });

    describe('Connection Management', () => {
        it('should connect to the correct URL', () => {
            const tenantId = 'test-tenant-123';
            StreamService.connect(tenantId);

            expect(consoleLogSpy).toHaveBeenCalledWith(
                expect.stringContaining(`/api/v1/stream/${tenantId}`)
            );
        });

        it('should set connected state to true on successful connection', async () => {
            const tenantId = 'test-tenant-123';

            expect(StreamService.isConnected()).toBe(false);

            StreamService.connect(tenantId);

            // Wait for async connection
            await new Promise(resolve => setTimeout(resolve, 10));

            expect(StreamService.isConnected()).toBe(true);
        });

        it('should not reconnect if already connected to same tenant', async () => {
            const tenantId = 'test-tenant-123';

            StreamService.connect(tenantId);
            await new Promise(resolve => setTimeout(resolve, 10));

            const callCountBefore = consoleLogSpy.mock.calls.length;

            // Try to connect again
            StreamService.connect(tenantId);

            // Should not create new connection
            expect(consoleLogSpy.mock.calls.length).toBe(callCountBefore);
        });

        it('should disconnect and reconnect when switching tenants', async () => {
            const tenant1 = 'tenant-1';
            const tenant2 = 'tenant-2';

            StreamService.connect(tenant1);
            await new Promise(resolve => setTimeout(resolve, 10));

            expect(StreamService.isConnected()).toBe(true);

            StreamService.connect(tenant2);

            expect(consoleLogSpy).toHaveBeenCalledWith('StreamService: Disconnected');
            expect(consoleLogSpy).toHaveBeenCalledWith(
                expect.stringContaining(`/api/v1/stream/${tenant2}`)
            );
        });

        it('should properly disconnect', async () => {
            const tenantId = 'test-tenant-123';

            StreamService.connect(tenantId);
            await new Promise(resolve => setTimeout(resolve, 10));

            expect(StreamService.isConnected()).toBe(true);

            StreamService.disconnect();

            expect(StreamService.isConnected()).toBe(false);
            expect(consoleLogSpy).toHaveBeenCalledWith('StreamService: Disconnected');
        });
    });

    describe('Event Handling', () => {
        it('should dispatch connected event on connection', async () => {
            const tenantId = 'test-tenant-123';
            const connectedCallback = vi.fn();

            StreamService.on('connected', connectedCallback);
            StreamService.connect(tenantId);

            await new Promise(resolve => setTimeout(resolve, 10));

            expect(connectedCallback).toHaveBeenCalledWith({ tenantId });
        });

        it('should parse and dispatch message events', async () => {
            const tenantId = 'test-tenant-123';
            const messageCallback = vi.fn();
            const testData = { metric: 'cpu', value: 75, timestamp: '2026-01-18T00:00:00Z' };

            StreamService.on('message', messageCallback);
            StreamService.connect(tenantId);

            await new Promise(resolve => setTimeout(resolve, 10));

            // Simulate message from server
            const eventSource = (StreamService as any).eventSource as MockEventSource;
            eventSource.simulateMessage(JSON.stringify(testData));

            expect(messageCallback).toHaveBeenCalledWith(testData);
        });

        it('should handle malformed JSON gracefully', async () => {
            const tenantId = 'test-tenant-123';
            const messageCallback = vi.fn();

            StreamService.on('message', messageCallback);
            StreamService.connect(tenantId);

            await new Promise(resolve => setTimeout(resolve, 10));

            const eventSource = (StreamService as any).eventSource as MockEventSource;
            eventSource.simulateMessage('invalid json {');

            expect(consoleErrorSpy).toHaveBeenCalledWith(
                'StreamService: Error parsing message',
                expect.any(Error)
            );
            expect(messageCallback).not.toHaveBeenCalled();
        });

        it('should dispatch error event on connection error', async () => {
            const tenantId = 'test-tenant-123';
            const errorCallback = vi.fn();

            StreamService.on('error', errorCallback);
            StreamService.connect(tenantId);

            await new Promise(resolve => setTimeout(resolve, 10));

            const eventSource = (StreamService as any).eventSource as MockEventSource;
            eventSource.simulateError();

            expect(errorCallback).toHaveBeenCalled();
            expect(StreamService.isConnected()).toBe(false);
        });

        it('should allow multiple listeners for the same event', async () => {
            const tenantId = 'test-tenant-123';
            const callback1 = vi.fn();
            const callback2 = vi.fn();

            StreamService.on('connected', callback1);
            StreamService.on('connected', callback2);
            StreamService.connect(tenantId);

            await new Promise(resolve => setTimeout(resolve, 10));

            expect(callback1).toHaveBeenCalled();
            expect(callback2).toHaveBeenCalled();
        });

        it('should remove listeners with off()', async () => {
            const tenantId = 'test-tenant-123';
            const callback = vi.fn();

            StreamService.on('connected', callback);
            StreamService.off('connected', callback);
            StreamService.connect(tenantId);

            await new Promise(resolve => setTimeout(resolve, 10));

            expect(callback).not.toHaveBeenCalled();
        });

        it('should handle off() for non-existent event gracefully', () => {
            const callback = vi.fn();

            expect(() => {
                StreamService.off('nonexistent', callback);
            }).not.toThrow();
        });
    });

    describe('State Management', () => {
        it('should update connected state on message receipt', async () => {
            const tenantId = 'test-tenant-123';

            StreamService.connect(tenantId);
            await new Promise(resolve => setTimeout(resolve, 10));

            // Simulate connection drop
            const eventSource = (StreamService as any).eventSource as MockEventSource;
            eventSource.simulateError();

            expect(StreamService.isConnected()).toBe(false);

            // Simulate message (reconnection)
            eventSource.simulateMessage(JSON.stringify({ test: 'data' }));

            expect(StreamService.isConnected()).toBe(true);
        });
    });
});
