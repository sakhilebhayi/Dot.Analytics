<?php

namespace App\Notifications;

use App\Models\ExecutiveBriefing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExecutiveBriefingReady extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ExecutiveBriefing $briefing) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $period = ucfirst($this->briefing->period);

        return (new MailMessage)
            ->subject("Your {$this->briefing->period} intelligence briefing is ready")
            ->line("{$period} briefing for {$this->briefing->period_date}:")
            ->line($this->briefing->summary)
            ->action('View full briefing', route('dashboard'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'briefing_ready',
            'title' => ucfirst($this->briefing->period).' briefing ready',
            'description' => $this->briefing->summary,
            'url' => route('dashboard'),
        ];
    }
}
