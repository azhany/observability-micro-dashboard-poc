<?php

namespace Tests\Feature;

use App\Console\Commands\MqttBridge;
use App\Jobs\ProcessMetricSubmission;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MqttBridgeTest extends TestCase
{
    use RefreshDatabase;

    protected MqttBridge $command;

    protected function setUp(): void
    {
        parent::setUp();

        // Create command instance and set up output
        $this->command = new MqttBridge;

        // Create a mock output interface for the command
        $output = $this->createMock(\Symfony\Component\Console\Output\OutputInterface::class);
        $formatter = $this->createMock(\Symfony\Component\Console\Formatter\OutputFormatterInterface::class);
        $output->method('getFormatter')->willReturn($formatter);

        // Use reflection to set the output property
        $reflection = new \ReflectionClass($this->command);
        $outputProperty = $reflection->getParentClass()->getProperty('output');
        $outputProperty->setAccessible(true);
        $outputProperty->setValue($this->command, $output);
    }

    /** @test */
    public function it_processes_valid_mqtt_message_and_dispatches_job()
    {
        Queue::fake();

        // Create a test tenant
        $tenant = Tenant::factory()->create();

        $topic = "metrics/{$tenant->id}/agent-001/cpu_usage";
        $message = json_encode([
            'value' => 75.5,
            'timestamp' => '2025-01-18 12:00:00',
            'dedupe_id' => 'test-123',
        ]);

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('processMessage');
        $method->setAccessible(true);

        // Process the message
        $method->invoke($this->command, $topic, $message);

        // Assert the job was dispatched
        Queue::assertPushed(ProcessMetricSubmission::class, function ($job) use ($tenant) {
            return $job->tenant->id === $tenant->id
                && count($job->metrics) === 1
                && $job->metrics[0]['agent_id'] === 'agent-001'
                && $job->metrics[0]['metric_name'] === 'cpu_usage'
                && $job->metrics[0]['value'] === 75.5
                && $job->metrics[0]['dedupe_id'] === 'test-123';
        });
    }

    /** @test */
    public function it_handles_message_without_optional_fields()
    {
        Queue::fake();

        $tenant = Tenant::factory()->create();

        $topic = "metrics/{$tenant->id}/agent-002/memory_usage";
        $message = json_encode([
            'value' => 512,
        ]);

        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('processMessage');
        $method->setAccessible(true);

        $method->invoke($this->command, $topic, $message);

        Queue::assertPushed(ProcessMetricSubmission::class, function ($job) use ($tenant) {
            return $job->tenant->id === $tenant->id
                && $job->metrics[0]['agent_id'] === 'agent-002'
                && $job->metrics[0]['metric_name'] === 'memory_usage'
                && $job->metrics[0]['value'] === 512
                && isset($job->metrics[0]['timestamp'])
                && $job->metrics[0]['dedupe_id'] === null;
        });
    }

    /** @test */
    public function it_rejects_invalid_topic_format()
    {
        Queue::fake();
        Log::shouldReceive('warning')->once();

        $topic = 'invalid/topic';
        $message = json_encode(['value' => 100]);

        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('processMessage');
        $method->setAccessible(true);

        $method->invoke($this->command, $topic, $message);

        Queue::assertNothingPushed();
    }

    /** @test */
    public function it_rejects_message_for_unknown_tenant()
    {
        Queue::fake();
        Log::shouldReceive('warning')->once();

        $fakeUuid = '00000000-0000-0000-0000-000000000000';
        $topic = "metrics/{$fakeUuid}/agent-001/cpu_usage";
        $message = json_encode(['value' => 100]);

        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('processMessage');
        $method->setAccessible(true);

        $method->invoke($this->command, $topic, $message);

        Queue::assertNothingPushed();
    }

    /** @test */
    public function it_rejects_invalid_json_payload()
    {
        Queue::fake();
        Log::shouldReceive('warning')->once();

        $tenant = Tenant::factory()->create();

        $topic = "metrics/{$tenant->id}/agent-001/cpu_usage";
        $message = 'invalid json{{{';

        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('processMessage');
        $method->setAccessible(true);

        $method->invoke($this->command, $topic, $message);

        Queue::assertNothingPushed();
    }

    /** @test */
    public function it_rejects_message_without_value_field()
    {
        Queue::fake();
        Log::shouldReceive('warning')->once();

        $tenant = Tenant::factory()->create();

        $topic = "metrics/{$tenant->id}/agent-001/cpu_usage";
        $message = json_encode(['timestamp' => '2025-01-18 12:00:00']);

        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('processMessage');
        $method->setAccessible(true);

        $method->invoke($this->command, $topic, $message);

        Queue::assertNothingPushed();
    }

    /** @test */
    public function it_handles_exceptions_gracefully()
    {
        Queue::fake();
        Log::shouldReceive('error')->once();

        // Create a topic that will cause an exception (malformed parts)
        $topic = 'metrics/invalid-uuid-format/agent/metric';
        $message = json_encode(['value' => 100]);

        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('processMessage');
        $method->setAccessible(true);

        // Should not throw exception
        $method->invoke($this->command, $topic, $message);

        Queue::assertNothingPushed();
    }
}
