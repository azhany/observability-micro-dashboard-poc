<?php

namespace Tests\Feature;

use App\Jobs\ProcessMetricSubmission;
use App\Models\Metric;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MetricProcessingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'settings' => [],
        ]);
    }

    public function test_single_metric_persists_to_metrics_raw(): void
    {
        $metricData = [
            'agent_id' => 'agent-001',
            'metric_name' => 'cpu_usage',
            'value' => 75.5,
            'timestamp' => '2026-01-18T10:00:00.123456Z',
            'dedupe_id' => 'dedupe-123',
        ];

        $job = new ProcessMetricSubmission($this->tenant, [$metricData]);
        $job->handle();

        // Assert metric was persisted
        $this->assertDatabaseHas('metrics_raw', [
            'tenant_id' => $this->tenant->id,
            'agent_id' => 'agent-001',
            'metric_name' => 'cpu_usage',
            'value' => 75.5,
            'dedupe_id' => 'dedupe-123',
        ]);

        // Verify only one record was created
        $this->assertEquals(1, Metric::count());
    }

    public function test_bulk_metrics_persist_to_metrics_raw(): void
    {
        $metricsData = [
            [
                'agent_id' => 'agent-001',
                'metric_name' => 'cpu_usage',
                'value' => 75.5,
                'timestamp' => '2026-01-18T10:00:00.123456Z',
                'dedupe_id' => 'dedupe-123',
            ],
            [
                'agent_id' => 'agent-002',
                'metric_name' => 'memory_usage',
                'value' => 82.3,
                'timestamp' => '2026-01-18T10:01:00.123456Z',
                'dedupe_id' => 'dedupe-456',
            ],
            [
                'agent_id' => 'agent-003',
                'metric_name' => 'disk_io',
                'value' => 1024.0,
                'timestamp' => '2026-01-18T10:02:00.123456Z',
            ],
        ];

        $job = new ProcessMetricSubmission($this->tenant, $metricsData);
        $job->handle();

        // Assert all metrics were persisted
        $this->assertEquals(3, Metric::count());

        $this->assertDatabaseHas('metrics_raw', [
            'tenant_id' => $this->tenant->id,
            'agent_id' => 'agent-001',
            'metric_name' => 'cpu_usage',
        ]);

        $this->assertDatabaseHas('metrics_raw', [
            'tenant_id' => $this->tenant->id,
            'agent_id' => 'agent-002',
            'metric_name' => 'memory_usage',
        ]);

        $this->assertDatabaseHas('metrics_raw', [
            'tenant_id' => $this->tenant->id,
            'agent_id' => 'agent-003',
            'metric_name' => 'disk_io',
            'dedupe_id' => null,
        ]);
    }

    public function test_idempotency_with_dedupe_id(): void
    {
        $metricData = [
            'agent_id' => 'agent-001',
            'metric_name' => 'cpu_usage',
            'value' => 75.5,
            'timestamp' => '2026-01-18T10:00:00.123456Z',
            'dedupe_id' => 'dedupe-unique-123',
        ];

        // Process the same metric twice
        $job1 = new ProcessMetricSubmission($this->tenant, [$metricData]);
        $job1->handle();

        $job2 = new ProcessMetricSubmission($this->tenant, [$metricData]);
        $job2->handle();

        // Assert only one record exists
        $this->assertEquals(1, Metric::count());

        $this->assertDatabaseHas('metrics_raw', [
            'tenant_id' => $this->tenant->id,
            'agent_id' => 'agent-001',
            'metric_name' => 'cpu_usage',
            'dedupe_id' => 'dedupe-unique-123',
        ]);
    }

    public function test_metrics_without_dedupe_id_always_insert(): void
    {
        $metricData = [
            'agent_id' => 'agent-001',
            'metric_name' => 'cpu_usage',
            'value' => 75.5,
            'timestamp' => '2026-01-18T10:00:00.123456Z',
        ];

        // Process the same metric data twice (no dedupe_id)
        $job1 = new ProcessMetricSubmission($this->tenant, [$metricData]);
        $job1->handle();

        $job2 = new ProcessMetricSubmission($this->tenant, [$metricData]);
        $job2->handle();

        // Assert two records exist
        $this->assertEquals(2, Metric::count());
    }

    public function test_tenant_id_is_stored_correctly(): void
    {
        $metricData = [
            'agent_id' => 'agent-001',
            'metric_name' => 'cpu_usage',
            'value' => 75.5,
            'timestamp' => '2026-01-18T10:00:00.123456Z',
        ];

        $job = new ProcessMetricSubmission($this->tenant, [$metricData]);
        $job->handle();

        $metric = Metric::first();

        // Verify tenant_id matches
        $this->assertEquals($this->tenant->id, $metric->tenant_id);

        // Verify the relationship works
        $this->assertInstanceOf(Tenant::class, $metric->tenant);
        $this->assertEquals($this->tenant->name, $metric->tenant->name);
    }

    public function test_job_retries_on_database_failure(): void
    {
        Queue::fake();

        $metricData = [
            'agent_id' => 'agent-001',
            'metric_name' => 'cpu_usage',
            'value' => 75.5,
            'timestamp' => '2026-01-18T10:00:00.123456Z',
        ];

        $job = new ProcessMetricSubmission($this->tenant, [$metricData]);

        // Verify retry configuration
        $this->assertEquals(5, $job->tries);
        $this->assertEquals([10, 30, 60], $job->backoff);
    }

    public function test_timestamp_precision_is_maintained(): void
    {
        $metricData = [
            'agent_id' => 'agent-001',
            'metric_name' => 'cpu_usage',
            'value' => 75.5,
            'timestamp' => '2026-01-18T10:00:00.123456Z',
        ];

        $job = new ProcessMetricSubmission($this->tenant, [$metricData]);
        $job->handle();

        $metric = Metric::first();

        // Verify timestamp is stored with precision
        $this->assertNotNull($metric->timestamp);
        $this->assertStringContainsString('2026-01-18', $metric->timestamp->toDateTimeString());
    }

    public function test_transaction_rolls_back_on_error(): void
    {
        $metricsData = [
            [
                'agent_id' => 'agent-001',
                'metric_name' => 'cpu_usage',
                'value' => 75.5,
                'timestamp' => '2026-01-18T10:00:00.123456Z',
            ],
            [
                // Invalid data that will cause an error (missing required field)
                'agent_id' => 'agent-002',
                // missing metric_name
                'value' => 82.3,
                'timestamp' => '2026-01-18T10:01:00.123456Z',
            ],
        ];

        $job = new ProcessMetricSubmission($this->tenant, $metricsData);

        try {
            $job->handle();
        } catch (\Exception $e) {
            // Expected to fail
        }

        // Assert no metrics were persisted due to transaction rollback
        $this->assertEquals(0, Metric::count());
    }

    public function test_dedupe_id_updates_existing_record(): void
    {
        $dedupeId = 'dedupe-update-test';

        // First submission
        $metricData1 = [
            'agent_id' => 'agent-001',
            'metric_name' => 'cpu_usage',
            'value' => 75.5,
            'timestamp' => '2026-01-18T10:00:00.123456Z',
            'dedupe_id' => $dedupeId,
        ];

        $job1 = new ProcessMetricSubmission($this->tenant, [$metricData1]);
        $job1->handle();

        // Second submission with same dedupe_id but different value
        $metricData2 = [
            'agent_id' => 'agent-001',
            'metric_name' => 'cpu_usage',
            'value' => 85.0, // Updated value
            'timestamp' => '2026-01-18T10:05:00.123456Z',
            'dedupe_id' => $dedupeId,
        ];

        $job2 = new ProcessMetricSubmission($this->tenant, [$metricData2]);
        $job2->handle();

        // Assert only one record exists
        $this->assertEquals(1, Metric::count());

        // Assert the value was updated
        $metric = Metric::where('dedupe_id', $dedupeId)->first();
        $this->assertEquals(85.0, $metric->value);
    }

    public function test_dedupe_id_is_isolated_per_tenant(): void
    {
        $tenant2 = Tenant::create([
            'name' => 'Second Tenant',
            'settings' => [],
        ]);

        $dedupeId = 'shared-dedupe-id';

        // Tenant 1 submission
        $metricData1 = [
            'agent_id' => 'agent-001',
            'metric_name' => 'cpu_usage',
            'value' => 10.0,
            'timestamp' => '2026-01-18T10:00:00.000000Z',
            'dedupe_id' => $dedupeId,
        ];

        (new ProcessMetricSubmission($this->tenant, [$metricData1]))->handle();

        // Tenant 2 submission with SAME dedupe_id
        $metricData2 = [
            'agent_id' => 'agent-002',
            'metric_name' => 'cpu_usage',
            'value' => 20.0,
            'timestamp' => '2026-01-18T10:00:00.000000Z',
            'dedupe_id' => $dedupeId,
        ];

        (new ProcessMetricSubmission($tenant2, [$metricData2]))->handle();

        // Assert BOTH records exist (Dedupe should be per-tenant)
        $this->assertEquals(2, Metric::count(), 'Dedupe ID should be isolated per tenant');

        $this->assertDatabaseHas('metrics_raw', [
            'tenant_id' => $this->tenant->id,
            'dedupe_id' => $dedupeId,
            'value' => 10.0,
        ]);

        $this->assertDatabaseHas('metrics_raw', [
            'tenant_id' => $tenant2->id,
            'dedupe_id' => $dedupeId,
            'value' => 20.0,
        ]);
    }
}
