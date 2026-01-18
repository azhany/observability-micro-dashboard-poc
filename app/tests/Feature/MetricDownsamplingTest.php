<?php

namespace Tests\Feature;

use App\Models\Metric;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MetricDownsamplingTest extends TestCase
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

    public function test_1m_downsample_calculates_correct_average(): void
    {
        // Create test data for a 1-minute window
        $baseTime = Carbon::now()->subMinutes(2)->startOfMinute();

        $metrics = [
            ['value' => 10.0],
            ['value' => 20.0],
            ['value' => 30.0],
        ];

        foreach ($metrics as $metric) {
            Metric::create([
                'tenant_id' => $this->tenant->id,
                'agent_id' => 'agent-001',
                'metric_name' => 'cpu_usage',
                'value' => $metric['value'],
                'timestamp' => $baseTime->copy()->addSeconds(rand(0, 59)),
            ]);
        }

        // Run the 1m downsample command with specific window
        Artisan::call('metrics:downsample', [
            'resolution' => '1m',
            '--window-start' => $baseTime->toDateTimeString(),
        ]);

        // Assert the average was calculated correctly (10 + 20 + 30) / 3 = 20.0
        $this->assertDatabaseHas('metrics_1m', [
            'tenant_id' => $this->tenant->id,
            'metric_name' => 'cpu_usage',
            'avg_value' => 20.0,
        ]);
    }

    public function test_5m_downsample_calculates_correct_average(): void
    {
        // Create test data for a 5-minute window
        $baseTime = Carbon::now()->subMinutes(10)->startOfMinute();
        // Align to 5-minute boundary
        $minute = $baseTime->minute;
        $alignedMinute = floor($minute / 5) * 5;
        $baseTime->minute($alignedMinute)->second(0);

        $metrics = [
            ['value' => 15.0],
            ['value' => 25.0],
            ['value' => 35.0],
            ['value' => 45.0],
        ];

        foreach ($metrics as $metric) {
            Metric::create([
                'tenant_id' => $this->tenant->id,
                'agent_id' => 'agent-001',
                'metric_name' => 'memory_usage',
                'value' => $metric['value'],
                'timestamp' => $baseTime->copy()->addMinutes(rand(0, 4)),
            ]);
        }

        // Run the 5m downsample command with specific window
        Artisan::call('metrics:downsample', [
            'resolution' => '5m',
            '--window-start' => $baseTime->toDateTimeString(),
        ]);

        // Assert the average was calculated correctly (15 + 25 + 35 + 45) / 4 = 30.0
        $this->assertDatabaseHas('metrics_5m', [
            'tenant_id' => $this->tenant->id,
            'metric_name' => 'memory_usage',
            'avg_value' => 30.0,
        ]);
    }

    public function test_downsample_groups_by_tenant_and_metric_name(): void
    {
        $tenant2 = Tenant::create([
            'name' => 'Second Tenant',
            'settings' => [],
        ]);

        $baseTime = Carbon::now()->subMinutes(2)->startOfMinute();

        // Create metrics for different tenants and metric names
        Metric::create([
            'tenant_id' => $this->tenant->id,
            'agent_id' => 'agent-001',
            'metric_name' => 'cpu_usage',
            'value' => 10.0,
            'timestamp' => $baseTime,
        ]);

        Metric::create([
            'tenant_id' => $this->tenant->id,
            'agent_id' => 'agent-001',
            'metric_name' => 'memory_usage',
            'value' => 20.0,
            'timestamp' => $baseTime,
        ]);

        Metric::create([
            'tenant_id' => $tenant2->id,
            'agent_id' => 'agent-002',
            'metric_name' => 'cpu_usage',
            'value' => 30.0,
            'timestamp' => $baseTime,
        ]);

        // Run the 1m downsample command with specific window
        Artisan::call('metrics:downsample', [
            'resolution' => '1m',
            '--window-start' => $baseTime->toDateTimeString(),
        ]);

        // Assert separate aggregations were created
        $this->assertEquals(3, DB::table('metrics_1m')->count());

        $this->assertDatabaseHas('metrics_1m', [
            'tenant_id' => $this->tenant->id,
            'metric_name' => 'cpu_usage',
            'avg_value' => 10.0,
        ]);

        $this->assertDatabaseHas('metrics_1m', [
            'tenant_id' => $this->tenant->id,
            'metric_name' => 'memory_usage',
            'avg_value' => 20.0,
        ]);

        $this->assertDatabaseHas('metrics_1m', [
            'tenant_id' => $tenant2->id,
            'metric_name' => 'cpu_usage',
            'avg_value' => 30.0,
        ]);
    }

    public function test_downsample_handles_no_data_gracefully(): void
    {
        // Run downsample with no data
        $exitCode = Artisan::call('metrics:downsample', ['resolution' => '1m']);

        // Assert command succeeded
        $this->assertEquals(0, $exitCode);

        // Assert no records were created
        $this->assertEquals(0, DB::table('metrics_1m')->count());
    }

    public function test_downsample_rejects_invalid_resolution(): void
    {
        $exitCode = Artisan::call('metrics:downsample', ['resolution' => 'invalid']);

        // Assert command failed
        $this->assertEquals(1, $exitCode);
    }

    public function test_downsample_updates_existing_aggregate(): void
    {
        $baseTime = Carbon::now()->subMinutes(2)->startOfMinute();

        // Create initial metric
        Metric::create([
            'tenant_id' => $this->tenant->id,
            'agent_id' => 'agent-001',
            'metric_name' => 'cpu_usage',
            'value' => 50.0,
            'timestamp' => $baseTime,
        ]);

        // Run downsample with specific window
        Artisan::call('metrics:downsample', [
            'resolution' => '1m',
            '--window-start' => $baseTime->toDateTimeString(),
        ]);

        // Verify initial aggregate
        $this->assertDatabaseHas('metrics_1m', [
            'tenant_id' => $this->tenant->id,
            'metric_name' => 'cpu_usage',
            'avg_value' => 50.0,
        ]);

        // Add another metric in the same window
        Metric::create([
            'tenant_id' => $this->tenant->id,
            'agent_id' => 'agent-001',
            'metric_name' => 'cpu_usage',
            'value' => 70.0,
            'timestamp' => $baseTime->copy()->addSeconds(30),
        ]);

        // Run downsample again with same window
        Artisan::call('metrics:downsample', [
            'resolution' => '1m',
            '--window-start' => $baseTime->toDateTimeString(),
        ]);

        // Verify aggregate was updated to new average (50 + 70) / 2 = 60.0
        $this->assertDatabaseHas('metrics_1m', [
            'tenant_id' => $this->tenant->id,
            'metric_name' => 'cpu_usage',
            'avg_value' => 60.0,
        ]);

        // Assert only one record exists
        $this->assertEquals(1, DB::table('metrics_1m')->count());
    }
}
