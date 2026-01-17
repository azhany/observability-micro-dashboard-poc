<?php

namespace Tests\Feature;

use App\Jobs\ProcessMetricSubmission;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class StreamTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'settings' => [],
        ]);

        $this->user = User::factory()->create();
    }

    public function test_unauthenticated_user_cannot_access_stream(): void
    {
        $response = $this->getJson("/api/v1/stream/{$this->tenant->id}");

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_access_stream(): void
    {
        $response = $this->actingAs($this->user)
            ->get("/api/v1/stream/{$this->tenant->id}");

        $response->assertStatus(200);
    }

    public function test_stream_returns_correct_sse_headers(): void
    {
        $response = $this->actingAs($this->user)
            ->get("/api/v1/stream/{$this->tenant->id}");

        $response->assertStatus(200);
        $this->assertStringContainsString('text/event-stream', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));
        $response->assertHeader('Connection', 'keep-alive');
        $response->assertHeader('X-Accel-Buffering', 'no');
    }

    public function test_stream_responds_with_404_for_nonexistent_tenant(): void
    {
        $fakeUuid = '00000000-0000-0000-0000-000000000000';

        $response = $this->actingAs($this->user)
            ->get("/api/v1/stream/{$fakeUuid}");

        $response->assertStatus(404);
    }

    public function test_process_metric_submission_publishes_to_redis(): void
    {
        $metricsData = [
            [
                'agent_id' => 'agent-001',
                'metric_name' => 'cpu_usage',
                'value' => 75.5,
                'timestamp' => '2026-01-18T10:00:00.123456Z',
            ],
            [
                'agent_id' => 'agent-002',
                'metric_name' => 'memory_usage',
                'value' => 82.3,
                'timestamp' => '2026-01-18T10:01:00.123456Z',
            ],
        ];

        $expectedChannel = "tenant.{$this->tenant->id}.metrics";
        $expectedPayload = json_encode($metricsData);

        Redis::shouldReceive('publish')
            ->once()
            ->with($expectedChannel, $expectedPayload);

        $job = new ProcessMetricSubmission($this->tenant, $metricsData);
        $job->handle();
    }

    public function test_redis_channel_uses_correct_tenant_uuid_pattern(): void
    {
        $metricData = [
            'agent_id' => 'agent-001',
            'metric_name' => 'cpu_usage',
            'value' => 75.5,
            'timestamp' => '2026-01-18T10:00:00.123456Z',
        ];

        $expectedChannel = "tenant.{$this->tenant->id}.metrics";

        Redis::shouldReceive('publish')
            ->once()
            ->withArgs(function ($channel, $payload) use ($expectedChannel) {
                return $channel === $expectedChannel
                    && str_starts_with($channel, 'tenant.')
                    && str_ends_with($channel, '.metrics');
            });

        $job = new ProcessMetricSubmission($this->tenant, [$metricData]);
        $job->handle();
    }

    public function test_published_payload_is_json_array_of_metrics(): void
    {
        $metricsData = [
            [
                'agent_id' => 'agent-001',
                'metric_name' => 'cpu_usage',
                'value' => 75.5,
                'timestamp' => '2026-01-18T10:00:00.123456Z',
            ],
        ];

        Redis::shouldReceive('publish')
            ->once()
            ->withArgs(function ($channel, $payload) {
                $decoded = json_decode($payload, true);

                return is_array($decoded)
                    && count($decoded) === 1
                    && $decoded[0]['agent_id'] === 'agent-001'
                    && $decoded[0]['metric_name'] === 'cpu_usage'
                    && $decoded[0]['value'] === 75.5
                    && $decoded[0]['timestamp'] === '2026-01-18T10:00:00.123456Z';
            });

        $job = new ProcessMetricSubmission($this->tenant, $metricsData);
        $job->handle();
    }

    public function test_bulk_metrics_publish_complete_array(): void
    {
        $metricsData = [
            [
                'agent_id' => 'agent-001',
                'metric_name' => 'cpu_usage',
                'value' => 75.5,
                'timestamp' => '2026-01-18T10:00:00.123456Z',
            ],
            [
                'agent_id' => 'agent-002',
                'metric_name' => 'memory_usage',
                'value' => 82.3,
                'timestamp' => '2026-01-18T10:01:00.123456Z',
            ],
            [
                'agent_id' => 'agent-003',
                'metric_name' => 'disk_io',
                'value' => 1024.0,
                'timestamp' => '2026-01-18T10:02:00.123456Z',
            ],
        ];

        Redis::shouldReceive('publish')
            ->once()
            ->withArgs(function ($channel, $payload) {
                $decoded = json_decode($payload, true);

                return count($decoded) === 3;
            });

        $job = new ProcessMetricSubmission($this->tenant, $metricsData);
        $job->handle();
    }
}
