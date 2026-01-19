<?php

namespace App\Notifications;

use App\Models\Alert;
use App\Models\Metric;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AlertFiringNotification extends Notification implements ShouldQueue
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
     * Get the webhook representation of the notification.
     */
    public function toWebhook(object $notifiable): array
    {
        return [
            'tenant_name' => $notifiable->name,
            'metric_name' => $this->alert->alertRule->metric_name,
            'value' => $this->metric->value,
            'threshold' => $this->alert->alertRule->threshold,
            'operator' => $this->alert->alertRule->operator,
            'timestamp' => $this->alert->started_at->toIso8601String(),
            'alert_id' => $this->alert->id,
        ];
    }
}
