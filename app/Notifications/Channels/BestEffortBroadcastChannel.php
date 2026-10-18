<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Channels\BroadcastChannel;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Laravel's broadcast channel, made optional.
 *
 * Real-time delivery needs a Reverb (WebSocket) server, which not every host can run
 * (the shared cPanel deployment cannot). The in-app copy is always written by the
 * `database` channel; this one only makes the bell update without a refresh. When the
 * server is down or never configured the broadcast fails, and that must cost nothing:
 * no exception reaching a request, no job retrying into failed_jobs.
 *
 * Only the exception class is logged. A broadcaster's message can embed the request
 * URL, which carries the app key and a request signature.
 */
class BestEffortBroadcastChannel
{
    public function __construct(private readonly BroadcastChannel $broadcast) {}

    public function send(mixed $notifiable, Notification $notification): void
    {
        try {
            $this->broadcast->send($notifiable, $notification);
        } catch (Throwable $e) {
            Log::warning('Real-time notification delivery skipped; the in-app copy is unaffected.', [
                'exception' => class_basename($e),
                'notification' => class_basename($notification),
            ]);
        }
    }
}
