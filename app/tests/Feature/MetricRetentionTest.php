<?php

namespace Tests\Feature;

use App\Models\Metric;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MetricRetentionTest extends TestCase
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

    public function test_cleanup_deletes_raw_metrics_older_than_24_hours(): void
    {
        // Create metrics older than 24 hours
        Metric::create([
            'tenant_id' => $this->tenant->id,
            'agent_id' => 'agent-001',
            'metric_name' => 'cpu_usage',
            'value' => 10.0,
            'timestamp' => Carbon::now()->subHours(25),
        ]);

        // Create recent metrics (should not be deleted)
        Metric::create([
            'tenant_id' => $this->tenant->id,
            'agent_id' => 'agent-001',
            'metric_name' => 'cpu_usage',
            'value' => 20.0,
            'timestamp' => Carbon::now()->subHours(12),
        ]);

        // Run cleanup command
        Artisan::call('metrics:cleanup');

        // Assert old metric was deleted
        $this->assertEquals(1, Metric::count());

        // Assert recent metric still exists
        $this->assertDatabaseHas('metrics_raw', [
            'tenant_id' => $this->tenant->id,
            'value' => 20.0,
        ]);

        // Assert old metric was deleted
        $this->assertDatabaseMissing('metrics_raw', [
            'tenant_id' => $this->tenant->id,
            'value' => 10.0,
        ]);
    }

    public function test_cleanup_deletes_1m_metrics_older_than_7_days(): void
    {
        // Create 1m metrics older than 7 days
        DB::table('metrics_1m')->insert([
            'tenant_id' => $this->tenant->id,
            'metric_name' => 'cpu_usage',
            'avg_value' => 10.0,
            'window_start' => Carbon::now()->subDays(8),
        ]);

        // Create recent 1m metrics (should not be deleted)
        DB::table('metrics_1m')->insert([
            'tenant_id' => $this->tenant->id,
            'metric_name' => 'cpu_usage',
            'avg_value' => 20.0,
            'window_start' => Carbon::now()->subDays(3),
        ]);

        // Run cleanup command
        Artisan::call('metrics:cleanup');

        // Assert old metric was deleted
        $this->assertEquals(1, DB::table('metrics_1m')->count());

        // Assert recent metric still exists
        $this->assertDatabaseHas('metrics_1m', [
            'tenant_id' => $this->tenant->id,
            'avg_value' => 20.0,
        ]);

        // Assert old metric was deleted
        $this->assertDatabaseMissing('metrics_1m', [
            'tenant_id' => $this->tenant->id,
            'avg_value' => 10.0,
        ]);
    }

    public function test_cleanup_deletes_5m_metrics_older_than_30_days(): void
    {
        // Create 5m metrics older than 30 days
        DB::table('metrics_5m')->insert([
            'tenant_id' => $this->tenant->id,
            'metric_name' => 'cpu_usage',
            'avg_value' => 10.0,
            'window_start' => Carbon::now()->subDays(35),
        ]);

        // Create recent 5m metrics (should not be deleted)
        DB::table('metrics_5m')->insert([
            'tenant_id' => $this->tenant->id,
            'metric_name' => 'cpu_usage',
            'avg_value' => 20.0,
            'window_start' => Carbon::now()->subDays(15),
        ]);

        // Run cleanup command
        Artisan::call('metrics:cleanup');

        // Assert old metric was deleted
        $this->assertEquals(1, DB::table('metrics_5m')->count());

        // Assert recent metric still exists
        $this->assertDatabaseHas('metrics_5m', [
            'tenant_id' => $this->tenant->id,
            'avg_value' => 20.0,
        ]);

        // Assert old metric was deleted
        $this->assertDatabaseMissing('metrics_5m', [
            'tenant_id' => $this->tenant->id,
            'avg_value' => 10.0,
        ]);
    }

    public function test_cleanup_handles_empty_tables_gracefully(): void
    {
        // Run cleanup with no data
        $exitCode = Artisan::call('metrics:cleanup');

        // Assert command succeeded
        $this->assertEquals(0, $exitCode);
    }

    public function test_cleanup_preserves_metrics_at_retention_boundary(): void
    {
        // Create raw metric exactly at 24-hour boundary
        Metric::create([
            'tenant_id' => $this->tenant->id,
            'agent_id' => 'agent-001',
            'metric_name' => 'cpu_usage',
            'value' => 10.0,
            'timestamp' => Carbon::now()->subDay(),
        ]);

        // Create 1m metric exactly at 7-day boundary
        DB::table('metrics_1m')->insert([
            'tenant_id' => $this->tenant->id,
            'metric_name' => 'memory_usage',
            'avg_value' => 20.0,
            'window_start' => Carbon::now()->subDays(7),
        ]);

        // Create 5m metric exactly at 30-day boundary
        DB::table('metrics_5m')->insert([
            'tenant_id' => $this->tenant->id,
            'metric_name' => 'disk_io',
            'avg_value' => 30.0,
            'window_start' => Carbon::now()->subDays(30),
        ]);

        // Run cleanup command
        Artisan::call('metrics:cleanup');

        // Assert metrics at the boundary are preserved (not deleted)
        $this->assertEquals(1, Metric::count());
        $this->assertEquals(1, DB::table('metrics_1m')->count());
        $this->assertEquals(1, DB::table('metrics_5m')->count());
    }

    public function test_cleanup_processes_multiple_tenants_correctly(): void
    {
        $tenant2 = Tenant::create([
            'name' => 'Second Tenant',
            'settings' => [],
        ]);

        // Create old metric for tenant 1
        Metric::create([
            'tenant_id' => $this->tenant->id,
            'agent_id' => 'agent-001',
            'metric_name' => 'cpu_usage',
            'value' => 10.0,
            'timestamp' => Carbon::now()->subHours(25),
        ]);

        // Create old metric for tenant 2
        Metric::create([
            'tenant_id' => $tenant2->id,
            'agent_id' => 'agent-002',
            'metric_name' => 'cpu_usage',
            'value' => 20.0,
            'timestamp' => Carbon::now()->subHours(25),
        ]);

        // Create recent metric for tenant 1
        Metric::create([
            'tenant_id' => $this->tenant->id,
            'agent_id' => 'agent-001',
            'metric_name' => 'cpu_usage',
            'value' => 30.0,
            'timestamp' => Carbon::now()->subHours(12),
        ]);

        // Run cleanup command
        Artisan::call('metrics:cleanup');

        // Assert both old metrics were deleted
        $this->assertEquals(1, Metric::count());

        // Assert only recent metric remains
        $this->assertDatabaseHas('metrics_raw', [
            'tenant_id' => $this->tenant->id,
            'value' => 30.0,
        ]);
    }

    public function test_cleanup_returns_success_status(): void
    {
        $exitCode = Artisan::call('metrics:cleanup');

        $this->assertEquals(0, $exitCode);
    }
}
