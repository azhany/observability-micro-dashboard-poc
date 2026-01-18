<?php

namespace App\Console\Commands;

use App\Jobs\ProcessMetricSubmission;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\Exceptions\MqttClientException;
use PhpMqtt\Client\MqttClient;

class MqttBridge extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mqtt:bridge
                            {--host=mosquitto : MQTT broker hostname}
                            {--port=1883 : MQTT broker port}
                            {--username=bridge_user : MQTT username for authentication}
                            {--password=bridge_pass : MQTT password for authentication}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Subscribe to MQTT metrics and forward to ingestion pipeline';

    /**
     * Flag to indicate if shutdown has been requested.
     */
    protected bool $shouldShutdown = false;

    /**
     * The MQTT client instance.
     */
    protected ?MqttClient $mqtt = null;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $host = $this->option('host');
        $port = (int) $this->option('port');
        $username = $this->option('username');
        $password = $this->option('password');

        $this->info("Starting MQTT Bridge: {$host}:{$port}");
        $this->info("Authenticating as: {$username}");

        // Register signal handlers for graceful shutdown
        $this->registerSignalHandlers();

        try {
            $this->mqtt = new MqttClient($host, $port, uniqid('observability_bridge_'));

            $connectionSettings = (new \PhpMqtt\Client\ConnectionSettings)
                ->setKeepAliveInterval(60)
                ->setLastWillTopic('bridge/status')
                ->setLastWillMessage('offline')
                ->setLastWillQualityOfService(1)
                ->setUsername($username)
                ->setPassword($password);

            $this->mqtt->connect($connectionSettings, true);
            $this->info('Connected to MQTT broker with authentication');

            // Subscribe to metrics wildcard topic
            $this->mqtt->subscribe('metrics/#', function (string $topic, string $message) {
                $this->processMessage($topic, $message);
            }, 1);

            $this->info('Subscribed to metrics/# - Listening for messages...');
            $this->info('Press Ctrl+C to gracefully shutdown');

            // Keep the connection alive and process incoming messages
            while (! $this->shouldShutdown) {
                $this->mqtt->loop(false, true, 1);
            }

            $this->info('Shutting down gracefully...');
            $this->mqtt->disconnect();
            $this->info('Disconnected from MQTT broker');
        } catch (MqttClientException $e) {
            $this->error('MQTT Error: '.$e->getMessage());
            Log::error('MQTT Bridge error', ['error' => $e->getMessage()]);

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Register signal handlers for graceful shutdown.
     */
    protected function registerSignalHandlers(): void
    {
        if (! function_exists('pcntl_signal')) {
            $this->warn('PCNTL extension not available - graceful shutdown disabled');

            return;
        }

        pcntl_signal(SIGTERM, function () {
            $this->info('Received SIGTERM signal');
            $this->shouldShutdown = true;
        });

        pcntl_signal(SIGINT, function () {
            $this->info('Received SIGINT signal');
            $this->shouldShutdown = true;
        });

        pcntl_async_signals(true);

        Log::debug('Signal handlers registered for graceful shutdown');
    }

    /**
     * Process an incoming MQTT message.
     */
    protected function processMessage(string $topic, string $message): void
    {
        try {
            // Parse topic: metrics/{tenant_id}/{agent_id}/{metric_name}
            $parts = explode('/', $topic);

            if (count($parts) !== 4 || $parts[0] !== 'metrics') {
                $this->warn("Invalid topic format: {$topic}");
                Log::warning('Invalid MQTT topic format', ['topic' => $topic]);

                return;
            }

            [, $tenantId, $agentId, $metricName] = $parts;

            // Validate tenant
            $tenant = Tenant::find($tenantId);
            if (! $tenant) {
                $this->warn("Tenant not found: {$tenantId}");
                Log::warning('MQTT message for unknown tenant', [
                    'tenant_id' => $tenantId,
                    'topic' => $topic,
                ]);

                return;
            }

            // Parse message payload
            $payload = json_decode($message, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->warn("Invalid JSON payload for topic {$topic}");
                Log::warning('Invalid MQTT message JSON', [
                    'topic' => $topic,
                    'error' => json_last_error_msg(),
                ]);

                return;
            }

            // Validate required fields
            if (! isset($payload['value'])) {
                $this->warn("Missing 'value' field in payload for topic {$topic}");
                Log::warning('MQTT message missing required fields', [
                    'topic' => $topic,
                    'payload' => $payload,
                ]);

                return;
            }

            // Prepare metric data
            $metricData = [
                'agent_id' => $agentId,
                'metric_name' => $metricName,
                'value' => $payload['value'],
                'timestamp' => $payload['timestamp'] ?? now()->toDateTimeString(),
                'dedupe_id' => $payload['dedupe_id'] ?? null,
            ];

            // Dispatch to ingestion pipeline
            ProcessMetricSubmission::dispatch($tenant, [$metricData]);

            $this->line("✓ Processed: {$topic} -> {$metricData['value']}");
            Log::debug('MQTT message processed', [
                'topic' => $topic,
                'tenant_id' => $tenant->id,
                'metric' => $metricData,
            ]);
        } catch (\Exception $e) {
            $this->error("Error processing message from {$topic}: {$e->getMessage()}");
            Log::error('MQTT message processing error', [
                'topic' => $topic,
                'message' => $message,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
