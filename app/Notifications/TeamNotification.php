<?php

namespace App\Notifications;

use App\Notifications\Channels\BestEffortBroadcastChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Common shape of the notifications a team's members receive.
 *
 * - Queued: sending never happens inside the request that caused it, and every channel is
 *   its own job, so a dead mail transport cannot hold back the in-app copy.
 * - `database` first, `mail` last: the in-app copy is the one that must always exist.
 * - The payload is deliberately small and explicit (title, a cut-down description, a link,
 *   the team). Alert context, snapshot payloads and provenance never leave the database.
 */
abstract class TeamNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** Longest description carried in mail and in the stored/broadcast payload. */
    protected const DESCRIPTION_LIMIT = 300;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    /** @return array{type: string, title: string, description: string, url: string, team_id: int} */
    abstract protected function payload(): array;

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        $channels = ['database', BestEffortBroadcastChannel::class];

        if ($this->canMail($notifiable)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /** @return array{type: string, title: string, description: string, url: string, team_id: int} */
    public function toArray(object $notifiable): array
    {
        return $this->payload();
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        // Attempt the push inline, inside this already-queued job, so the best-effort
        // channel can swallow an outage instead of a second queued job failing and retrying.
        return (new BroadcastMessage($this->payload()))->onConnection('sync');
    }

    /** A notification is only mailable when there is somewhere to send it and something to send it with. */
    protected function canMail(object $notifiable): bool
    {
        $mailer = config('mail.default');

        return filled($notifiable->email ?? null)
            && filled($mailer)
            && is_array(config("mail.mailers.{$mailer}"));
    }

    protected function excerpt(?string $text): string
    {
        return Str::limit(trim((string) $text), self::DESCRIPTION_LIMIT, '...');
    }
}
