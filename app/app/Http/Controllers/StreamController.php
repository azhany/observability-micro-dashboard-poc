<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StreamController extends Controller
{
    /**
     * Stream metrics for a specific tenant using Server-Sent Events (SSE).
     */
    public function stream(string $tenantId): StreamedResponse
    {
        $tenant = Tenant::findOrFail($tenantId);

        // Authorization check: Ensure user has access to this tenant
        $user = auth()->user();
        if (!$user->tenants()->where('tenants.id', $tenant->id)->exists()) {
            abort(403, 'Unauthorized access to tenant stream');
        }

        $response = new StreamedResponse(function () use ($tenant) {
            $channel = "tenant.{$tenant->id}.metrics";

            Log::info('SSE stream started', ['tenant_id' => $tenant->id, 'channel' => $channel]);

            // PERF-001 Mitigation: Limit connection duration to prevent worker starvation
            $maxDuration = 60; // seconds
            $heartbeatInterval = 15; // seconds
            $startTime = time();
            $lastHeartbeat = $startTime;

            set_time_limit($maxDuration + 5); // Add buffer for cleanup
            ob_implicit_flush(true);

            $redis = Redis::connection();
            $pubsub = $redis->pubSubLoop();
            $pubsub->subscribe($channel);

            try {
                foreach ($pubsub as $message) {
                    $currentTime = time();

                    // Check if max duration exceeded
                    if (($currentTime - $startTime) >= $maxDuration) {
                        Log::info('SSE stream max duration reached', ['tenant_id' => $tenant->id]);
                        break;
                    }

                    // Send heartbeat to keep connection alive
                    if (($currentTime - $lastHeartbeat) >= $heartbeatInterval) {
                        echo ": heartbeat\n\n";
                        $lastHeartbeat = $currentTime;

                        if (ob_get_level() > 0) {
                            ob_flush();
                        }
                        flush();
                    }

                    if ($message->kind === 'message') {
                        $data = $message->payload;

                        echo "data: {$data}\n\n";

                        if (ob_get_level() > 0) {
                            ob_flush();
                        }
                        flush();

                        if (connection_aborted()) {
                            break;
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::error('SSE stream error', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                ]);
            } finally {
                $pubsub->unsubscribe();
                Log::info('SSE stream closed', ['tenant_id' => $tenant->id]);
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }
}
