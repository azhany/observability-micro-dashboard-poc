<?php

namespace App\Jobs;

use App\Models\Metric;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class ProcessMetricSubmission implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 5;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public $backoff = [10, 30, 60];

    /**
     * The tenant associated with this metric submission.
     */
    public Tenant $tenant;

    /**
     * The array of metric payloads to process.
     */
    public array $metrics;

    /**
     * Create a new job instance.
     */
    public function __construct(Tenant $tenant, array $metrics)
    {
        $this->tenant = $tenant;
        $this->metrics = $metrics;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $metricsToUpsert = [];
        $metricsToInsert = [];

        foreach ($this->metrics as $metricData) {
            $data = [
                'tenant_id' => $this->tenant->id,
                'agent_id' => $metricData['agent_id'],
                'metric_name' => $metricData['metric_name'],
                'value' => $metricData['value'],
                'timestamp' => $metricData['timestamp'],
                'dedupe_id' => $metricData['dedupe_id'] ?? null,
            ];

            if (! empty($data['dedupe_id'])) {
                $metricsToUpsert[] = $data;
            } else {
                $metricsToInsert[] = $data;
            }
        }

        DB::transaction(function () use ($metricsToUpsert, $metricsToInsert) {
            if (! empty($metricsToUpsert)) {
                Metric::upsert(
                    $metricsToUpsert,
                    ['tenant_id', 'dedupe_id'],
                    ['agent_id', 'metric_name', 'value', 'timestamp']
                );
            }

            if (! empty($metricsToInsert)) {
                Metric::insert($metricsToInsert);
            }
        });

        Log::info('Processed metric submission', [
            'tenant_id' => $this->tenant->id,
            'metric_count' => count($this->metrics),
        ]);

        $this->publishToRedis();
    }

    /**
     * Publish processed metrics to Redis Pub/Sub for SSE streaming.
     */
    protected function publishToRedis(): void
    {
        $channel = "tenant.{$this->tenant->id}.metrics";

        $metricsPayload = array_map(function ($metricData) {
            return [
                'agent_id' => $metricData['agent_id'],
                'metric_name' => $metricData['metric_name'],
                'value' => $metricData['value'],
                'timestamp' => $metricData['timestamp'],
            ];
        }, $this->metrics);

        Redis::publish($channel, json_encode($metricsPayload));

        Log::debug('Published metrics to Redis', [
            'channel' => $channel,
            'metric_count' => count($metricsPayload),
        ]);
    }
}
