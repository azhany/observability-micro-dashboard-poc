<?php

namespace Tests\Feature;

use App\Jobs\ProcessMetricSubmission;
use App\Models\Tenant;
use App\Models\TenantToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class MetricIngestionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a tenant and token for testing
        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'settings' => [],
        ]);

        $this->token = Str::random(64);

        TenantToken::create([
            'tenant_id' => $this->tenant->id,
            'token' => hash('sha256', $this->token),
        ]);
    }

    public function test_valid_single_metric_payload_returns_202_and_dispatches_job(): void
    {
        Queue::fake();

        $payload = [
            'metric_name' => 'cpu_usage',
            'value' => 75.5,
            'timestamp' => '2026-01-18T10:00:00Z',
            'agent_id' => 'agent-001',
            'dedupe_id' => 'dedupe-123',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
        ])->postJson('/api/v1/metrics', $payload);

        $response->assertStatus(202)
            ->assertJson([
                'status' => 'accepted',
            ]);

        // Assert the job was dispatched
        Queue::assertPushed(ProcessMetricSubmission::class, function ($job) use ($payload) {
            // Verify tenant_id is correctly passed
            return $job->tenant->id === $this->tenant->id
                && $job->metrics === [$payload]; // Single metric wrapped in array
        });
    }

    public function test_valid_bulk_metric_payload_returns_202_and_dispatches_job(): void
    {
        Queue::fake();

        $payload = [
            [
                'metric_name' => 'cpu_usage',
                'value' => 75.5,
                'timestamp' => '2026-01-18T10:00:00Z',
            ],
            [
                'metric_name' => 'memory_usage',
                'value' => 82.3,
                'timestamp' => '2026-01-18T10:01:00Z',
                'agent_id' => 'agent-002',
            ],
            [
                'metric_name' => 'disk_io',
                'value' => 1024,
                'timestamp' => '2026-01-18T10:02:00Z',
                'dedupe_id' => 'dedupe-456',
            ],
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
        ])->postJson('/api/v1/metrics', $payload);

        $response->assertStatus(202)
            ->assertJson([
                'status' => 'accepted',
            ]);

        // Assert the job was dispatched
        Queue::assertPushed(ProcessMetricSubmission::class, function ($job) use ($payload) {
            // Verify tenant_id is correctly passed
            return $job->tenant->id === $this->tenant->id
                && $job->metrics === $payload
                && count($job->metrics) === 3;
        });
    }

    public function test_missing_metric_name_returns_422(): void
    {
        Queue::fake();

        $payload = [
            'value' => 75.5,
            'timestamp' => '2026-01-18T10:00:00Z',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
        ])->postJson('/api/v1/metrics', $payload);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'error',
                'errors' => ['metric_name'],
            ]);

        // Assert no job was dispatched
        Queue::assertNotPushed(ProcessMetricSubmission::class);
    }

    public function test_missing_value_returns_422(): void
    {
        Queue::fake();

        $payload = [
            'metric_name' => 'cpu_usage',
            'timestamp' => '2026-01-18T10:00:00Z',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
        ])->postJson('/api/v1/metrics', $payload);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'error',
                'errors' => ['value'],
            ]);

        // Assert no job was dispatched
        Queue::assertNotPushed(ProcessMetricSubmission::class);
    }

    public function test_invalid_value_type_returns_422(): void
    {
        Queue::fake();

        $payload = [
            'metric_name' => 'cpu_usage',
            'value' => 'not-a-number',
            'timestamp' => '2026-01-18T10:00:00Z',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
        ])->postJson('/api/v1/metrics', $payload);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'error',
                'errors' => ['value'],
            ]);

        // Assert no job was dispatched
        Queue::assertNotPushed(ProcessMetricSubmission::class);
    }

    public function test_missing_timestamp_returns_422(): void
    {
        Queue::fake();

        $payload = [
            'metric_name' => 'cpu_usage',
            'value' => 75.5,
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
        ])->postJson('/api/v1/metrics', $payload);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'error',
                'errors' => ['timestamp'],
            ]);

        // Assert no job was dispatched
        Queue::assertNotPushed(ProcessMetricSubmission::class);
    }

    public function test_invalid_timestamp_format_returns_422(): void
    {
        Queue::fake();

        $payload = [
            'metric_name' => 'cpu_usage',
            'value' => 75.5,
            'timestamp' => 'not-a-date',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
        ])->postJson('/api/v1/metrics', $payload);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'error',
                'errors' => ['timestamp'],
            ]);

        // Assert no job was dispatched
        Queue::assertNotPushed(ProcessMetricSubmission::class);
    }

    public function test_metric_name_exceeding_max_length_returns_422(): void
    {
        Queue::fake();

        $payload = [
            'metric_name' => str_repeat('a', 65), // 65 characters (max is 64)
            'value' => 75.5,
            'timestamp' => '2026-01-18T10:00:00Z',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
        ])->postJson('/api/v1/metrics', $payload);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'error',
                'errors' => ['metric_name'],
            ]);

        // Assert no job was dispatched
        Queue::assertNotPushed(ProcessMetricSubmission::class);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        Queue::fake();

        $payload = [
            'metric_name' => 'cpu_usage',
            'value' => 75.5,
            'timestamp' => '2026-01-18T10:00:00Z',
        ];

        // Make request without Authorization header
        $response = $this->postJson('/api/v1/metrics', $payload);

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'Unauthorized',
            ]);

        // Assert no job was dispatched
        Queue::assertNotPushed(ProcessMetricSubmission::class);
    }

    public function test_invalid_token_returns_401(): void
    {
        Queue::fake();

        $payload = [
            'metric_name' => 'cpu_usage',
            'value' => 75.5,
            'timestamp' => '2026-01-18T10:00:00Z',
        ];

        // Make request with invalid token
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid_token_here',
        ])->postJson('/api/v1/metrics', $payload);

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'Unauthorized',
            ]);

        // Assert no job was dispatched
        Queue::assertNotPushed(ProcessMetricSubmission::class);
    }

    public function test_bulk_payload_with_invalid_item_returns_422(): void
    {
        Queue::fake();

        $payload = [
            [
                'metric_name' => 'cpu_usage',
                'value' => 75.5,
                'timestamp' => '2026-01-18T10:00:00Z',
            ],
            [
                // Missing metric_name in second item
                'value' => 82.3,
                'timestamp' => '2026-01-18T10:01:00Z',
            ],
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
        ])->postJson('/api/v1/metrics', $payload);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'error',
                'errors',
            ]);

        // Assert no job was dispatched
        Queue::assertNotPushed(ProcessMetricSubmission::class);
    }

    public function test_tenant_id_is_correctly_passed_to_job(): void
    {
        Queue::fake();

        // Create a second tenant to ensure proper isolation
        $tenant2 = Tenant::create([
            'name' => 'Second Tenant',
            'settings' => [],
        ]);

        $token2 = Str::random(64);

        TenantToken::create([
            'tenant_id' => $tenant2->id,
            'token' => hash('sha256', $token2),
        ]);

        $payload = [
            'metric_name' => 'cpu_usage',
            'value' => 75.5,
            'timestamp' => '2026-01-18T10:00:00Z',
        ];

        // Submit with first tenant
        $response1 = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
        ])->postJson('/api/v1/metrics', $payload);

        $response1->assertStatus(202);

        // Submit with second tenant
        $response2 = $this->withHeaders([
            'Authorization' => 'Bearer '.$token2,
        ])->postJson('/api/v1/metrics', $payload);

        $response2->assertStatus(202);

        // Assert jobs were dispatched with correct tenant IDs
        Queue::assertPushed(ProcessMetricSubmission::class, 2);

        Queue::assertPushed(ProcessMetricSubmission::class, function ($job) {
            return $job->tenant->id === $this->tenant->id;
        });

        Queue::assertPushed(ProcessMetricSubmission::class, function ($job) use ($tenant2) {
            return $job->tenant->id === $tenant2->id;
        });
    }
}
