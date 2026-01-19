<?php

namespace Tests\Feature;

use App\Models\Metric;
use App\Models\Tenant;
use App\Models\TenantToken;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MetricQueryApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private string $apiToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'settings' => [],
        ]);

        $token = TenantToken::create([
            'tenant_id' => $this->tenant->id,
            'token' => hash('sha256', 'test-token'),
            'name' => 'Test Token',
            'expires_at' => null,
        ]);

        $this->apiToken = 'test-token';
    }

    public function test_query_raw_metrics_by_default(): void
    {
        // Create raw metrics
        Metric::create([
            'tenant_id' => $this->tenant->id,
            'agent_id' => 'agent-001',
            'metric_name' => 'cpu_usage',
            'value' => 75.5,
            'timestamp' => Carbon::now()->subMinutes(5),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->apiToken,
        ])->getJson('/api/v1/metrics');

        $response->assertStatus(200)
            ->assertJson([
                'resolution' => 'raw',
                'count' => 1,
            ])
            ->assertJsonPath('data.0.metric_name', 'cpu_usage')
            ->assertJsonPath('data.0.value', 75.5);
    }

    public function test_query_1m_resolution_metrics(): void
    {
        // Create 1m aggregated metrics
        DB::table('metrics_1m')->insert([
            'tenant_id' => $this->tenant->id,
            'metric_name' => 'cpu_usage',
            'avg_value' => 60.0,
            'window_start' => Carbon::now()->subMinutes(10),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->apiToken,
        ])->getJson('/api/v1/metrics?resolution=1m');

        $response->assertStatus(200)
            ->assertJson([
                'resolution' => '1m',
                'count' => 1,
            ])
            ->assertJsonPath('data.0.metric_name', 'cpu_usage');

        // Check value (may be returned as int or float)
        $this->assertEquals(60.0, $response->json('data.0.value'));
    }

    public function test_query_5m_resolution_metrics(): void
    {
        // Create 5m aggregated metrics
        DB::table('metrics_5m')->insert([
            'tenant_id' => $this->tenant->id,
            'metric_name' => 'memory_usage',
            'avg_value' => 85.5,
            'window_start' => Carbon::now()->subMinutes(30),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->apiToken,
        ])->getJson('/api/v1/metrics?resolution=5m');

        $response->assertStatus(200)
            ->assertJson([
                'resolution' => '5m',
                'count' => 1,
            ])
            ->assertJsonPath('data.0.metric_name', 'memory_usage')
            ->assertJsonPath('data.0.value', 85.5);
    }

    public function test_query_filters_by_metric_name(): void
    {
        Metric::create([
            'tenant_id' => $this->tenant->id,
            'agent_id' => 'agent-001',
            'metric_name' => 'cpu_usage',
            'value' => 75.5,
            'timestamp' => Carbon::now(),
        ]);

        Metric::create([
            'tenant_id' => $this->tenant->id,
            'agent_id' => 'agent-001',
            'metric_name' => 'memory_usage',
            'value' => 85.5,
            'timestamp' => Carbon::now(),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->apiToken,
        ])->getJson('/api/v1/metrics?metric_name=cpu_usage');

        $response->assertStatus(200)
            ->assertJson(['count' => 1])
            ->assertJsonPath('data.0.metric_name', 'cpu_usage');
    }

    public function test_query_filters_by_time_range(): void
    {
        $now = Carbon::now();

        // Old metric (outside range)
        Metric::create([
            'tenant_id' => $this->tenant->id,
            'agent_id' => 'agent-001',
            'metric_name' => 'cpu_usage',
            'value' => 50.0,
            'timestamp' => $now->copy()->subHours(2),
        ]);

        // Recent metric (inside range)
        Metric::create([
            'tenant_id' => $this->tenant->id,
            'agent_id' => 'agent-001',
            'metric_name' => 'cpu_usage',
            'value' => 75.0,
            'timestamp' => $now->copy()->subMinutes(30),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->apiToken,
        ])->getJson('/api/v1/metrics?start_time='.$now->copy()->subHour()->toDateTimeString());

        $response->assertStatus(200)
            ->assertJson(['count' => 1]);

        // Check value (may be returned as int or float)
        $this->assertEquals(75.0, $response->json('data.0.value'));
    }

    public function test_query_limits_results_to_1000(): void
    {
        // Create 1500 metrics
        for ($i = 0; $i < 1500; $i++) {
            Metric::create([
                'tenant_id' => $this->tenant->id,
                'agent_id' => 'agent-001',
                'metric_name' => 'cpu_usage',
                'value' => 50.0 + $i,
                'timestamp' => Carbon::now()->subMinutes($i),
            ]);
        }

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->apiToken,
        ])->getJson('/api/v1/metrics');

        $response->assertStatus(200)
            ->assertJson(['count' => 1000]);
    }

    public function test_query_returns_metrics_in_descending_time_order(): void
    {
        $now = Carbon::now();

        Metric::create([
            'tenant_id' => $this->tenant->id,
            'agent_id' => 'agent-001',
            'metric_name' => 'cpu_usage',
            'value' => 10.0,
            'timestamp' => $now->copy()->subMinutes(10),
        ]);

        Metric::create([
            'tenant_id' => $this->tenant->id,
            'agent_id' => 'agent-001',
            'metric_name' => 'cpu_usage',
            'value' => 20.0,
            'timestamp' => $now->copy()->subMinutes(5),
        ]);

        Metric::create([
            'tenant_id' => $this->tenant->id,
            'agent_id' => 'agent-001',
            'metric_name' => 'cpu_usage',
            'value' => 30.0,
            'timestamp' => $now->copy()->subMinutes(2),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->apiToken,
        ])->getJson('/api/v1/metrics');

        $response->assertStatus(200);

        // Most recent first
        $data = $response->json('data');
        $this->assertEquals(30.0, $data[0]['value']);
        $this->assertEquals(20.0, $data[1]['value']);
        $this->assertEquals(10.0, $data[2]['value']);
    }

    public function test_query_isolates_metrics_by_tenant(): void
    {
        $tenant2 = Tenant::create([
            'name' => 'Second Tenant',
            'settings' => [],
        ]);

        TenantToken::create([
            'tenant_id' => $tenant2->id,
            'token' => hash('sha256', 'tenant2-token'),
            'name' => 'Tenant 2 Token',
            'expires_at' => null,
        ]);

        // Create metrics for both tenants
        Metric::create([
            'tenant_id' => $this->tenant->id,
            'agent_id' => 'agent-001',
            'metric_name' => 'cpu_usage',
            'value' => 10.0,
            'timestamp' => Carbon::now(),
        ]);

        Metric::create([
            'tenant_id' => $tenant2->id,
            'agent_id' => 'agent-002',
            'metric_name' => 'cpu_usage',
            'value' => 20.0,
            'timestamp' => Carbon::now(),
        ]);

        // Query as tenant 1
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->apiToken,
        ])->getJson('/api/v1/metrics');

        $response->assertStatus(200)
            ->assertJson(['count' => 1]);

        // Check value (may be returned as int or float)
        $this->assertEquals(10.0, $response->json('data.0.value'));

        // Query as tenant 2
        $response2 = $this->withHeaders([
            'Authorization' => 'Bearer tenant2-token',
        ])->getJson('/api/v1/metrics');

        $response2->assertStatus(200)
            ->assertJson(['count' => 1]);

        // Check value (may be returned as int or float)
        $this->assertEquals(20.0, $response2->json('data.0.value'));
    }

    public function test_query_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/metrics');

        $response->assertStatus(401);
    }

    public function test_query_validates_resolution_parameter(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->apiToken,
        ])->getJson('/api/v1/metrics?resolution=invalid');

        $response->assertStatus(422)
            ->assertJsonValidationErrors('resolution');
    }

    public function test_query_returns_empty_result_when_no_metrics_exist(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->apiToken,
        ])->getJson('/api/v1/metrics');

        $response->assertStatus(200)
            ->assertJson([
                'resolution' => 'raw',
                'count' => 0,
                'data' => [],
            ]);
    }

    public function test_query_filters_by_agent_id(): void
    {
        // Create metrics for different agents
        Metric::create([
            'tenant_id' => $this->tenant->id,
            'agent_id' => 'agent-001',
            'metric_name' => 'cpu_usage',
            'value' => 50.0,
            'timestamp' => Carbon::now(),
        ]);

        Metric::create([
            'tenant_id' => $this->tenant->id,
            'agent_id' => 'agent-002',
            'metric_name' => 'cpu_usage',
            'value' => 75.0,
            'timestamp' => Carbon::now(),
        ]);

        Metric::create([
            'tenant_id' => $this->tenant->id,
            'agent_id' => 'agent-003',
            'metric_name' => 'cpu_usage',
            'value' => 90.0,
            'timestamp' => Carbon::now(),
        ]);

        // Query for specific agent
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->apiToken,
        ])->getJson('/api/v1/metrics?agent_id=agent-002');

        $response->assertStatus(200)
            ->assertJson(['count' => 1])
            ->assertJsonPath('data.0.agent_id', 'agent-002');

        $this->assertEquals(75.0, $response->json('data.0.value'));
    }
}
