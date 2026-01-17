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

        $response = new StreamedResponse(function () use ($tenant) {
            $channel = "tenant.{$tenant->id}.metrics";

            Log::info('SSE stream started', ['tenant_id' => $tenant->id, 'channel' => $channel]);

            set_time_limit(0);
            ob_implicit_flush(true);

            $redis = Redis::connection();
            $pubsub = $redis->pubSubLoop();
            $pubsub->subscribe($channel);

            try {
                foreach ($pubsub as $message) {
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
