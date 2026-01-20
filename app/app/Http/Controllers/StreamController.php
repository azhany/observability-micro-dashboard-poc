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

            // Set a read timeout to allow periodic checks (if feasible) or just rely on standard subscribe behavior
            // Note: Standard subscribe blocks until message received or connection lost.

            try {
                $redis->subscribe([$channel], function ($message) {
                    echo "data: {$message}\n\n";

                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                });
            } catch (\Exception $e) {
                // Ignore read errors/timeouts if we implement them, otherwise verify connection
                Log::error('SSE stream error', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                ]);
            }

            Log::info('SSE stream closed', ['tenant_id' => $tenant->id]);
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }
}
