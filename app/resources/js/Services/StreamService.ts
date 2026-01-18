
type StreamCallback = (data: any) => void;

class StreamService {
    private static instance: StreamService;
    private eventSource: EventSource | null = null;
    private listeners: Map<string, StreamCallback[]> = new Map();
    private currentTenantId: string | null = null;
    private connected = false;

    private constructor() { }

    public static getInstance(): StreamService {
        if (!StreamService.instance) {
            StreamService.instance = new StreamService();
        }
        return StreamService.instance;
    }

    public isConnected(): boolean {
        return this.connected;
    }

    public connect(tenantId: string): void {
        // If already connected to the same tenant, do nothing
        if (this.currentTenantId === tenantId && this.eventSource?.readyState === EventSource.OPEN) {
            return;
        }

        // Connect to new tenant
        this.disconnect();

        this.currentTenantId = tenantId;
        const url = `/api/v1/stream/${tenantId}`;
        console.log(`StreamService: Connecting to ${url}`);

        this.eventSource = new EventSource(url);

        this.eventSource.onopen = () => {
            console.log('StreamService: Connected');
            this.connected = true;
            this.dispatch('connected', { tenantId });
        };

        this.eventSource.onmessage = (event) => {
            try {
                const payload = JSON.parse(event.data);
                this.connected = true;
                this.dispatch('message', payload);
            } catch (error) {
                console.error('StreamService: Error parsing message', error);
            }
        };

        this.eventSource.onerror = (error) => {
            console.error('StreamService: Connection error', error);
            this.connected = false;
            this.dispatch('error', error);
            // EventSource automatically attempts to reconnect
        };
    }

    public disconnect(): void {
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
            this.currentTenantId = null;
            this.connected = false;
            console.log('StreamService: Disconnected');
        }
    }

    public on(event: string, callback: StreamCallback): void {
        if (!this.listeners.has(event)) {
            this.listeners.set(event, []);
        }
        this.listeners.get(event)?.push(callback);
    }

    public off(event: string, callback: StreamCallback): void {
        if (!this.listeners.has(event)) return;

        const callbacks = this.listeners.get(event);
        if (callbacks) {
            this.listeners.set(event, callbacks.filter(cb => cb !== callback));
        }
    }

    private dispatch(event: string, data: any): void {
        const callbacks = this.listeners.get(event);
        if (callbacks) {
            callbacks.forEach(cb => cb(data));
        }
    }
}

export default StreamService.getInstance();
