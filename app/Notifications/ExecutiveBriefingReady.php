<?php

namespace App\Notifications;

use App\Models\ExecutiveBriefing;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * A briefing that was actually written from real insights and metrics. Unavailable
 * briefings never reach here (TeamNotifier refuses them).
 */
class ExecutiveBriefingReady extends TeamNotification
{
    public function __construct(public readonly ExecutiveBriefing $briefing) {}

    public function toMail(object $notifiable): MailMessage
    {
        $period = ucfirst($this->briefing->period);

        return (new MailMessage)
            ->subject("Your {$this->briefing->period} intelligence briefing is ready")
            ->line("{$period} briefing for {$this->briefing->period_date}:")
            ->line($this->excerpt($this->briefing->summary))
            ->action('View full briefing', route('dashboard'));
    }

    protected function payload(): array
    {
        return [
            'type' => 'briefing_ready',
            'title' => ucfirst($this->briefing->period).' briefing ready',
            'description' => $this->excerpt($this->briefing->summary),
            'url' => route('dashboard'),
            'team_id' => $this->briefing->team_id,
        ];
    }
}
