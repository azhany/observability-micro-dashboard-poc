<?php

namespace Tests\Feature;

use App\Jobs\EvaluateAlertRules;
use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\Metric;
use App\Models\Tenant;
use App\Notifications\AlertFiringNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationDispatchTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private AlertRule $alertRule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'settings' => [
                'webhook_url' => 'https://example.com/webhook',
                'notification_email' => 'admin@example.com',
            ],
        ]);

        $this->alertRule = AlertRule::create([
            'tenant_id' => $this->tenant->id,
            'metric_name' => 'cpu_usage',
            'operator' => '>',
            'threshold' => 90.0,
            'duration' => 60,
        ]);
    }

    public function test_webhook_is_called_when_alert_starts_firing(): void
    {
        Http::fake();
        Mail::fake();

        $startTime = Carbon::now()->subMinutes(2);

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

        Http::assertSent(function ($request) {
            return $request->url() === 'https://example.com/webhook'
                && $request['tenant_name'] === 'Test Tenant'
                && $request['metric_name'] === 'cpu_usage'
                && $request['value'] == 95.0
                && $request['threshold'] == 90.0
                && $request['operator'] === '>';
        });
    }

    public function test_email_is_sent_when_alert_starts_firing(): void
    {
        Notification::fake();

        $startTime = Carbon::now()->subMinutes(2);

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

        Notification::assertSentTo(
            $this->tenant,
            AlertFiringNotification::class,
            function ($notification, $channels) {
                return in_array('mail', $channels)
                    && $notification->alert->alertRule->metric_name === 'cpu_usage';
            }
        );
    }

    public function test_no_notification_sent_when_alert_remains_firing(): void
    {
        Http::fake();
        Mail::fake();

        Alert::create([
            'tenant_id' => $this->tenant->id,
            'alert_rule_id' => $this->alertRule->id,
            'state' => 'FIRING',
            'started_at' => now()->subMinutes(5),
            'last_checked_at' => now()->subMinutes(1),
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

        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_no_notification_sent_when_alert_is_pending(): void
    {
        Http::fake();
        Mail::fake();

        Alert::create([
            'tenant_id' => $this->tenant->id,
            'alert_rule_id' => $this->alertRule->id,
            'state' => 'PENDING',
            'started_at' => now()->subSeconds(30),
            'last_checked_at' => now()->subSeconds(30),
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

        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_no_notification_sent_when_tenant_has_no_webhook_or_email_configured(): void
    {
        Http::fake();
        Mail::fake();

        $tenantWithoutSettings = Tenant::create([
            'name' => 'Tenant Without Settings',
            'settings' => [],
        ]);

        $rule = AlertRule::create([
            'tenant_id' => $tenantWithoutSettings->id,
            'metric_name' => 'cpu_usage',
            'operator' => '>',
            'threshold' => 90.0,
            'duration' => 60,
        ]);

        $startTime = Carbon::now()->subMinutes(2);

        Alert::create([
            'tenant_id' => $tenantWithoutSettings->id,
            'alert_rule_id' => $rule->id,
            'state' => 'PENDING',
            'started_at' => $startTime,
            'last_checked_at' => $startTime,
        ]);

        Metric::create([
            'tenant_id' => $tenantWithoutSettings->id,
            'agent_id' => 'agent-001',
            'metric_name' => 'cpu_usage',
            'value' => 95.0,
            'timestamp' => now(),
        ]);

        $job = new EvaluateAlertRules;
        $job->handle();

        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_notification_payload_contains_correct_data(): void
    {
        Http::fake();
        Mail::fake();

        $startTime = Carbon::now()->subMinutes(2);

        Alert::create([
            'tenant_id' => $this->tenant->id,
            'alert_rule_id' => $this->alertRule->id,
            'state' => 'PENDING',
            'started_at' => $startTime,
            'last_checked_at' => $startTime,
        ]);

        $testMetric = Metric::create([
            'tenant_id' => $this->tenant->id,
            'agent_id' => 'agent-001',
            'metric_name' => 'cpu_usage',
            'value' => 95.5,
            'timestamp' => now(),
        ]);

        $job = new EvaluateAlertRules;
        $job->handle();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://example.com/webhook'
                && $request['tenant_name'] === 'Test Tenant'
                && $request['metric_name'] === 'cpu_usage'
                && $request['value'] === 95.5
                && $request['threshold'] === 90.0
                && $request['operator'] === '>'
                && isset($request['timestamp'])
                && isset($request['alert_id']);
        });
    }

    public function test_webhook_only_notification_when_email_not_configured(): void
    {
        Http::fake();
        Mail::fake();

        $tenantWebhookOnly = Tenant::create([
            'name' => 'Webhook Only Tenant',
            'settings' => [
                'webhook_url' => 'https://example.com/webhook',
            ],
        ]);

        $rule = AlertRule::create([
            'tenant_id' => $tenantWebhookOnly->id,
            'metric_name' => 'cpu_usage',
            'operator' => '>',
            'threshold' => 90.0,
            'duration' => 60,
        ]);

        $startTime = Carbon::now()->subMinutes(2);

        Alert::create([
            'tenant_id' => $tenantWebhookOnly->id,
            'alert_rule_id' => $rule->id,
            'state' => 'PENDING',
            'started_at' => $startTime,
            'last_checked_at' => $startTime,
        ]);

        Metric::create([
            'tenant_id' => $tenantWebhookOnly->id,
            'agent_id' => 'agent-001',
            'metric_name' => 'cpu_usage',
            'value' => 95.0,
            'timestamp' => now(),
        ]);

        $job = new EvaluateAlertRules;
        $job->handle();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://example.com/webhook';
        });

        Mail::assertNothingSent();
    }

    public function test_email_only_notification_when_webhook_not_configured(): void
    {
        Notification::fake();

        $tenantEmailOnly = Tenant::create([
            'name' => 'Email Only Tenant',
            'settings' => [
                'notification_email' => 'admin@example.com',
            ],
        ]);

        $rule = AlertRule::create([
            'tenant_id' => $tenantEmailOnly->id,
            'metric_name' => 'cpu_usage',
            'operator' => '>',
            'threshold' => 90.0,
            'duration' => 60,
        ]);

        $startTime = Carbon::now()->subMinutes(2);

        Alert::create([
            'tenant_id' => $tenantEmailOnly->id,
            'alert_rule_id' => $rule->id,
            'state' => 'PENDING',
            'started_at' => $startTime,
            'last_checked_at' => $startTime,
        ]);

        Metric::create([
            'tenant_id' => $tenantEmailOnly->id,
            'agent_id' => 'agent-001',
            'metric_name' => 'cpu_usage',
            'value' => 95.0,
            'timestamp' => now(),
        ]);

        $job = new EvaluateAlertRules;
        $job->handle();

        Notification::assertSentTo(
            $tenantEmailOnly,
            AlertFiringNotification::class,
            function ($notification, $channels) {
                return $channels === ['mail'];
            }
        );
    }
}
