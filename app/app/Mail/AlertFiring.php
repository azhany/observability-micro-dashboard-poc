<?php

namespace App\Mail;

use App\Models\Alert;
use App\Models\Metric;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AlertFiring extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Alert $alert,
        public Metric $metric,
        public Tenant $tenant
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $settings = $this->tenant->settings ?? [];
        $email = $settings['notification_email'] ?? 'admin@example.com';

        return new Envelope(
            to: [new Address($email)],
            subject: "[ALERT] {$this->alert->alertRule->metric_name} is FIRING for {$this->tenant->name}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.alert-firing',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
