<?php

namespace App\Jobs;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessMetricSubmission implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
     *
     * @param Tenant $tenant
     * @param array $metrics
     */
    public function __construct(Tenant $tenant, array $metrics)
    {
        $this->tenant = $tenant;
        $this->metrics = $metrics;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        // Log the payload for now (DB persistence is in Story 1.4)
        Log::info('Processing metric submission', [
            'tenant_id' => $this->tenant->id,
            'tenant_name' => $this->tenant->name,
            'metric_count' => count($this->metrics),
            'metrics' => $this->metrics,
        ]);

        // TODO: Story 1.4 will implement actual database persistence
    }
}
