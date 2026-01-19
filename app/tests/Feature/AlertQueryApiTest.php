<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\Tenant;
use App\Models\TenantToken;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertQueryApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private AlertRule $alertRule;
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

        $this->alertRule = AlertRule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'metric_name' => 'cpu_usage',
            'operator' => '>',
            'threshold' => 80,
            'duration' => 60,
        ]);
    }

    public function test_can_fetch_alerts_for_tenant(): void
    {
        // Create test alerts
        Alert::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'alert_rule_id' => $this->alertRule->id,
            'state' => 'FIRING',
        ]);

        $response = $this->withToken($this->apiToken)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->getJson('/api/v1/alerts');

        $response->assertOk()
            ->assertJsonStructure([
                'count',
                'data' => [
                    '*' => [
                        'id',
                        'metric_name',
                        'state',
                        'started_at',
                        'last_checked_at',
                        'threshold',
                        'operator',
                    ]
                ]
            ])
            ->assertJsonPath('count', 3);
    }

    public function test_can_filter_alerts_by_metric_name(): void
    {
        $cpuAlertRule = AlertRule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'metric_name' => 'cpu_usage',
        ]);

        $memoryAlertRule = AlertRule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'metric_name' => 'memory_usage',
        ]);

        Alert::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'alert_rule_id' => $cpuAlertRule->id,
        ]);

        Alert::factory()->create([
            'tenant_id' => $this->tenant->id,
            'alert_rule_id' => $memoryAlertRule->id,
        ]);

        $response = $this->withToken($this->apiToken)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->getJson('/api/v1/alerts?metric_name=cpu_usage');

        $response->assertOk()
            ->assertJsonPath('count', 2);
    }

    public function test_can_filter_alerts_by_state(): void
    {
        Alert::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'alert_rule_id' => $this->alertRule->id,
            'state' => 'FIRING',
        ]);

        Alert::factory()->create([
            'tenant_id' => $this->tenant->id,
            'alert_rule_id' => $this->alertRule->id,
            'state' => 'OK',
        ]);

        $response = $this->withToken($this->apiToken)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->getJson('/api/v1/alerts?state=FIRING');

        $response->assertOk()
            ->assertJsonPath('count', 2);
    }

    public function test_can_filter_alerts_by_time_range(): void
    {
        $now = Carbon::now();

        // Recent alert
        Alert::factory()->create([
            'tenant_id' => $this->tenant->id,
            'alert_rule_id' => $this->alertRule->id,
            'started_at' => $now->copy()->subMinutes(30),
        ]);

        // Old alert
        Alert::factory()->create([
            'tenant_id' => $this->tenant->id,
            'alert_rule_id' => $this->alertRule->id,
            'started_at' => $now->copy()->subHours(25),
        ]);

        $response = $this->withToken($this->apiToken)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->getJson('/api/v1/alerts?start_time='.$now->copy()->subHour()->toDateTimeString());

        $response->assertOk()
            ->assertJsonPath('count', 1);
    }

    public function test_alerts_are_scoped_to_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherAlertRule = AlertRule::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);

        // Create alerts for both tenants
        Alert::factory()->create([
            'tenant_id' => $this->tenant->id,
            'alert_rule_id' => $this->alertRule->id,
        ]);

        Alert::factory()->create([
            'tenant_id' => $otherTenant->id,
            'alert_rule_id' => $otherAlertRule->id,
        ]);

        $response = $this->withToken($this->apiToken)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->getJson('/api/v1/alerts');

        $response->assertOk()
            ->assertJsonPath('count', 1);
    }

    public function test_requires_tenant_authentication(): void
    {
        $response = $this->getJson('/api/v1/alerts');

        $response->assertUnauthorized();
    }

    public function test_validates_state_parameter(): void
    {
        $response = $this->withToken($this->apiToken)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->getJson('/api/v1/alerts?state=INVALID');

        $response->assertStatus(422);
    }
}
