<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;

class WebhookChannel
{
    /**
     * Send the given notification.
     */
    public function send(object $notifiable, Notification $notification): void
    {
        if (method_exists($notification, 'toWebhook')) {
            $notification->toWebhook($notifiable);
        }
    }
}
