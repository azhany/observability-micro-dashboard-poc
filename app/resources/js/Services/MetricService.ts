
export interface MetricDataPoint {
    metric_name: string;
    value: number;
    timestamp: string;
}

export interface MetricQueryParams {
    resolution?: 'raw' | '1m' | '5m';
    metric_name?: string;
    start_time?: string;
    end_time?: string;
    agent_id?: string;
}

export interface MetricResponse {
    resolution: string;
    count: number;
    data: MetricDataPoint[];
}

export interface AlertDataPoint {
    id: string;
    metric_name: string;
    state: 'OK' | 'PENDING' | 'FIRING';
    started_at: string;
    last_checked_at: string;
    threshold: number;
    operator: string;
}

export interface AlertQueryParams {
    metric_name?: string;
    start_time?: string;
    end_time?: string;
    state?: 'OK' | 'PENDING' | 'FIRING';
}

export interface AlertResponse {
    count: number;
    data: AlertDataPoint[];
    limit?: number;
    has_more?: boolean;
}

class MetricService {
    private baseUrl = '/api/v1/metrics';
    private alertUrl = '/api/v1/alerts';

    /**
     * Fetch historical metrics from the API
     */
    async fetchHistoricalMetrics(
        tenantId: string,
        params: MetricQueryParams
    ): Promise<MetricResponse> {
        const queryParams = new URLSearchParams();

        if (params.resolution) {
            queryParams.append('resolution', params.resolution);
        }
        if (params.metric_name) {
            queryParams.append('metric_name', params.metric_name);
        }
        if (params.agent_id) {
            queryParams.append('agent_id', params.agent_id);
        }
        if (params.start_time) {
            queryParams.append('start_time', params.start_time);
        }
        if (params.end_time) {
            queryParams.append('end_time', params.end_time);
        }

        const url = `${this.baseUrl}?${queryParams.toString()}`;

        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Tenant-ID': tenantId,
            },
        });

        if (!response.ok) {
            throw new Error(`Failed to fetch metrics: ${response.statusText}`);
        }

        return await response.json();
    }

    /**
     * Calculate start and end times based on time range
     */
    getTimeRangeForHours(hours: number): { start_time: string; end_time: string } {
        const now = new Date();
        const start = new Date(now.getTime() - hours * 60 * 60 * 1000);

        return {
            start_time: start.toISOString(),
            end_time: now.toISOString(),
        };
    }

    /**
     * Fetch alert history from the API
     */
    async fetchAlerts(
        tenantId: string,
        params: AlertQueryParams
    ): Promise<AlertResponse> {
        const queryParams = new URLSearchParams();

        if (params.metric_name) {
            queryParams.append('metric_name', params.metric_name);
        }
        if (params.start_time) {
            queryParams.append('start_time', params.start_time);
        }
        if (params.end_time) {
            queryParams.append('end_time', params.end_time);
        }
        if (params.state) {
            queryParams.append('state', params.state);
        }

        const url = `${this.alertUrl}?${queryParams.toString()}`;

        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Tenant-ID': tenantId,
            },
        });

        if (!response.ok) {
            throw new Error(`Failed to fetch alerts: ${response.statusText}`);
        }

        return await response.json();
    }
}

export default new MetricService();
