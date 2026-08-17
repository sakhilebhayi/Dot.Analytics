<?php

namespace App\Notifications;

use App\Models\AnalyticsAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class CriticalAlertTriggered extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly AnalyticsAlert $alert) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Critical alert: {$this->alert->title}")
            ->line($this->alert->description)
            ->action('View in Dot.Analytics', route('dashboard'))
            ->line('This is a critical-severity alert on your team\'s intelligence dashboard.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'critical_alert',
            'title' => $this->alert->title,
            'description' => $this->alert->description,
            'url' => route('dashboard'),
        ];
    }
}
