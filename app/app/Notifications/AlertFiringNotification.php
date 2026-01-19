<?php

namespace App\Notifications;

use App\Models\Alert;
use App\Models\Metric;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AlertFiringNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Alert $alert,
        public Metric $metric
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = [];
        $settings = $notifiable->settings ?? [];

        if (! empty($settings['webhook_url'])) {
            $channels[] = 'webhook';
        }

        if (! empty($settings['notification_email'])) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("[ALERT] {$this->alert->alertRule->metric_name} is FIRING for {$notifiable->name}")
            ->greeting('Alert Notification')
            ->line('🔥 **Alert is FIRING**')
            ->line("**Tenant:** {$notifiable->name}")
            ->line("**Metric:** {$this->alert->alertRule->metric_name}")
            ->line("**Current Value:** {$this->metric->value}")
            ->line("**Threshold:** {$this->alert->alertRule->operator} {$this->alert->alertRule->threshold}")
            ->line("**Started At:** {$this->alert->started_at->format('Y-m-d H:i:s T')}")
            ->line("**Alert ID:** {$this->alert->id}")
            ->line("This alert was triggered because the metric **{$this->alert->alertRule->metric_name}** has exceeded the configured threshold.")
            ->salutation('This is an automated notification from the Observability Dashboard.');
    }

    /**
     * Send webhook notification.
     */
    public function toWebhook(object $notifiable): void
    {
        $settings = $notifiable->settings ?? [];
        $webhookUrl = $settings['webhook_url'] ?? null;

        if (! $webhookUrl) {
            return;
        }

        $payload = [
            'tenant_name' => $notifiable->name,
            'metric_name' => $this->alert->alertRule->metric_name,
            'value' => $this->metric->value,
            'threshold' => $this->alert->alertRule->threshold,
            'operator' => $this->alert->alertRule->operator,
            'timestamp' => $this->alert->started_at->toIso8601String(),
            'alert_id' => $this->alert->id,
        ];

        try {
            $response = Http::timeout(10)->post($webhookUrl, $payload);

            if ($response->successful()) {
                Log::info('Webhook notification sent successfully', [
                    'tenant_id' => $notifiable->id,
                    'alert_id' => $this->alert->id,
                    'webhook_url' => $webhookUrl,
                ]);
            } else {
                Log::warning('Webhook notification failed', [
                    'tenant_id' => $notifiable->id,
                    'alert_id' => $this->alert->id,
                    'webhook_url' => $webhookUrl,
                    'status' => $response->status(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Webhook notification exception', [
                'tenant_id' => $notifiable->id,
                'alert_id' => $this->alert->id,
                'webhook_url' => $webhookUrl,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
