<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookChannel
{
    /**
     * Send the given notification.
     */
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toWebhook')) {
            return;
        }

        $settings = $notifiable->settings ?? [];
        $webhookUrl = $settings['webhook_url'] ?? null;

        if (! $webhookUrl) {
            return;
        }

        $payload = $notification->toWebhook($notifiable);

        try {
            $response = Http::timeout(10)->post($webhookUrl, $payload);

            if ($response->successful()) {
                Log::info('Webhook notification sent successfully', [
                    'tenant_id' => $notifiable->id,
                    'webhook_url' => $webhookUrl,
                ]);
            } else {
                Log::warning('Webhook notification failed', [
                    'tenant_id' => $notifiable->id,
                    'webhook_url' => $webhookUrl,
                    'status' => $response->status(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Webhook notification exception', [
                'tenant_id' => $notifiable->id,
                'webhook_url' => $webhookUrl,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
