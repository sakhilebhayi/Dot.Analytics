<?php

namespace App\Notifications;

use App\Models\AnalyticsAlert;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * A critical alert backed by real data: a critical cross-platform insight or a critical
 * metric anomaly. Sent by TeamNotifier, which decides whether an alert qualifies.
 */
class CriticalAlertTriggered extends TeamNotification
{
    public function __construct(public readonly AnalyticsAlert $alert) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Critical alert: {$this->alert->title}")
            ->line($this->excerpt($this->alert->description))
            ->action('View in Dot.Analytics', route('dashboard'))
            ->line('You are receiving this because you are a member of the team this critical alert was raised for.');
    }

    protected function payload(): array
    {
        return [
            'type' => 'critical_alert',
            'title' => $this->alert->title,
            'description' => $this->excerpt($this->alert->description),
            'url' => route('dashboard'),
            'team_id' => $this->alert->team_id,
        ];
    }
}
