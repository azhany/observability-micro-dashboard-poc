<?php

namespace Tests\Feature;

use App\Jobs\EvaluateAlertRules;
use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\Metric;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertEvaluationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private AlertRule $alertRule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'settings' => [],
        ]);

        $this->alertRule = AlertRule::create([
            'tenant_id' => $this->tenant->id,
            'metric_name' => 'cpu_usage',
            'operator' => '>',
            'threshold' => 90.0,
            'duration' => 60,
        ]);
    }

    public function test_alert_transitions_from_ok_to_pending_when_threshold_breached(): void
    {
        Metric::create([
            'tenant_id' => $this->tenant->id,
            'agent_id' => 'agent-001',
            'metric_name' => 'cpu_usage',
            'value' => 95.0,
            'timestamp' => now(),
        ]);

        $job = new EvaluateAlertRules;
        $job->handle();

        $this->assertDatabaseHas('alerts', [
            'tenant_id' => $this->tenant->id,
            'alert_rule_id' => $this->alertRule->id,
            'state' => 'PENDING',
        ]);
    }

    public function test_alert_transitions_from_pending_to_firing_after_duration(): void
    {
        $startTime = Carbon::now()->subMinutes(2);

        \DB::table('alerts')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'alert_rule_id' => $this->alertRule->id,
            'state' => 'PENDING',
            'started_at' => $startTime->toDateTimeString(),
            'last_checked_at' => $startTime->toDateTimeString(),
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);

        Metric::create([
            'tenant_id' => $this->tenant->id,
            'agent_id' => 'agent-001',
            'metric_name' => 'cpu_usage',
            'value' => 95.0,
            'timestamp' => now(),
        ]);

        $job = new EvaluateAlertRules;
        $job->handle();

        $firingAlert = Alert::where('alert_rule_id', $this->alertRule->id)
            ->where('state', 'FIRING')
            ->first();

        $this->assertNotNull($firingAlert, 'Alert should have transitioned to FIRING');
    }

    public function test_alert_does_not_fire_before_duration_met(): void
    {
        $startTime = now()->subSeconds(30);

        Alert::create([
            'tenant_id' => $this->tenant->id,
            'alert_rule_id' => $this->alertRule->id,
            'state' => 'PENDING',
            'started_at' => $startTime,
            'last_checked_at' => $startTime,
        ]);

        Metric::create([
            'tenant_id' => $this->tenant->id,
            'agent_id' => 'agent-001',
            'metric_name' => 'cpu_usage',
            'value' => 95.0,
            'timestamp' => now(),
        ]);

        $job = new EvaluateAlertRules;
        $job->handle();

        $this->assertDatabaseMissing('alerts', [
            'tenant_id' => $this->tenant->id,
            'alert_rule_id' => $this->alertRule->id,
            'state' => 'FIRING',
        ]);

        $alert = Alert::where('alert_rule_id', $this->alertRule->id)
            ->orderBy('created_at', 'desc')
            ->first();

        $this->assertEquals('PENDING', $alert->state);
    }

    public function test_alert_transitions_to_ok_when_threshold_no_longer_breached(): void
    {
        $this->freezeTime();

        Alert::create([
            'tenant_id' => $this->tenant->id,
            'alert_rule_id' => $this->alertRule->id,
            'state' => 'FIRING',
            'started_at' => now()->subMinutes(5),
            'last_checked_at' => now()->subMinutes(1),
        ]);

        $this->travel(1)->seconds();

        Metric::create([
            'tenant_id' => $this->tenant->id,
            'agent_id' => 'agent-001',
            'metric_name' => 'cpu_usage',
            'value' => 50.0,
            'timestamp' => now(),
        ]);

        $job = new EvaluateAlertRules;
        $job->handle();

        $alert = Alert::where('alert_rule_id', $this->alertRule->id)
            ->where('state', 'OK')
            ->first();

        $this->assertNotNull($alert, 'Alert should have transitioned to OK');
        $this->assertEquals('OK', $alert->state);
    }

    public function test_alerts_are_isolated_by_tenant(): void
    {
        $otherTenant = Tenant::create([
            'name' => 'Other Tenant',
            'settings' => [],
        ]);

        $otherAlertRule = AlertRule::create([
            'tenant_id' => $otherTenant->id,
            'metric_name' => 'cpu_usage',
            'operator' => '>',
            'threshold' => 90.0,
            'duration' => 60,
        ]);

        Metric::create([
            'tenant_id' => $this->tenant->id,
            'agent_id' => 'agent-001',
            'metric_name' => 'cpu_usage',
            'value' => 95.0,
            'timestamp' => now(),
        ]);

        Metric::create([
            'tenant_id' => $otherTenant->id,
            'agent_id' => 'agent-002',
            'metric_name' => 'cpu_usage',
            'value' => 50.0,
            'timestamp' => now(),
        ]);

        $job = new EvaluateAlertRules;
        $job->handle();

        $this->assertDatabaseHas('alerts', [
            'tenant_id' => $this->tenant->id,
            'alert_rule_id' => $this->alertRule->id,
            'state' => 'PENDING',
        ]);

        $this->assertDatabaseMissing('alerts', [
            'tenant_id' => $otherTenant->id,
            'alert_rule_id' => $otherAlertRule->id,
        ]);
    }

    public function test_threshold_operators_work_correctly(): void
    {
        $testCases = [
            ['operator' => '>', 'threshold' => 90.0, 'value' => 95.0, 'shouldTrigger' => true],
            ['operator' => '>', 'threshold' => 90.0, 'value' => 85.0, 'shouldTrigger' => false],
            ['operator' => '<', 'threshold' => 10.0, 'value' => 5.0, 'shouldTrigger' => true],
            ['operator' => '<', 'threshold' => 10.0, 'value' => 15.0, 'shouldTrigger' => false],
            ['operator' => '>=', 'threshold' => 90.0, 'value' => 90.0, 'shouldTrigger' => true],
            ['operator' => '<=', 'threshold' => 10.0, 'value' => 10.0, 'shouldTrigger' => true],
            ['operator' => '=', 'threshold' => 50.0, 'value' => 50.0, 'shouldTrigger' => true],
        ];

        foreach ($testCases as $index => $testCase) {
            $rule = AlertRule::create([
                'tenant_id' => $this->tenant->id,
                'metric_name' => 'test_metric_'.$index,
                'operator' => $testCase['operator'],
                'threshold' => $testCase['threshold'],
                'duration' => 60,
            ]);

            Metric::create([
                'tenant_id' => $this->tenant->id,
                'agent_id' => 'agent-001',
                'metric_name' => 'test_metric_'.$index,
                'value' => $testCase['value'],
                'timestamp' => now(),
            ]);

            $job = new EvaluateAlertRules;
            $job->handle();

            if ($testCase['shouldTrigger']) {
                $alert = Alert::where('alert_rule_id', $rule->id)->where('state', 'PENDING')->first();
                $this->assertNotNull($alert, "Operator {$testCase['operator']} should have triggered alert");
            } else {
                $alert = Alert::where('alert_rule_id', $rule->id)->first();
                $this->assertNull($alert, "Operator {$testCase['operator']} should not have triggered alert");
            }
        }
    }
}
