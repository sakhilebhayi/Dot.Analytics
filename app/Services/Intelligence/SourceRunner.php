<?php

namespace App\Services\Intelligence;

use App\Models\Intelligence\Source;
use App\Models\Intelligence\SourceRun;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs one collection from a source and records how it went. A failure leaves stored data alone, keeps the source's
 * place, and retries after 15 minutes, then 30, then twice as long again, never longer than the source's own interval, so
 * a broken feed is neither hammered nor forgotten.
 */
class SourceRunner
{
    private const FIRST_RETRY_MINUTES = 15;

    private const FAILING_AFTER = 3;

    public function run(Source $source, int $seconds = 40): SourceRun
    {
        $run = SourceRun::create(['source_id' => $source->id, 'started_at' => now(), 'status' => 'running']);
        $collector = new Collector($source, $run);
        $source->forceFill(['last_attempt_at' => now()])->save();

        try {
            /** @var Fetcher $fetcher */
            $fetcher = app($source->fetcher);
            $cursor = $fetcher->fetch($source, $collector, $source->cursor ?? [], new Deadline($seconds));
            $collector->flush();
            $this->succeeded($source, $run, $collector, $cursor);
        } catch (SourceFailed $e) {
            $collector->flush();
            $this->failed($source, $run, $collector, $e->getMessage(), $e->httpStatus, $e->retryAfterSeconds);
        } catch (Throwable $e) {
            Log::error('A source collection broke.', ['source' => $source->key, 'error' => $e::class, 'message' => $e->getMessage()]);
            $this->failed($source, $run, $collector, 'The collection stopped unexpectedly.', null, null);
        }

        return $run->refresh();
    }

    /** @param  array<string, mixed>  $cursor */
    private function succeeded(Source $source, SourceRun $run, Collector $collector, array $cursor): void
    {
        $unfinished = ($cursor['more'] ?? false) === true;

        $run->update(['status' => $unfinished ? 'partial' : 'succeeded', 'finished_at' => now(), ...$collector->counts]);
        $source->forceFill([
            'status' => 'healthy',
            'cursor' => $cursor,
            'consecutive_failures' => 0,
            'last_error' => null,
            // A pass that stored something and has more to do is still a collection: the data is there and fresh.
            'last_success_at' => $unfinished && ($collector->counts['created'] + $collector->counts['updated'] + $collector->counts['unchanged']) === 0 ? $source->last_success_at : now(),
            'next_due_at' => $unfinished ? now()->addMinute() : now()->addMinutes($source->collect_every_minutes),
        ])->save();
    }

    private function failed(Source $source, SourceRun $run, Collector $collector, string $message, ?int $status, ?int $retryAfter): void
    {
        $failures = $source->consecutive_failures + 1;
        $waitMinutes = min($source->collect_every_minutes, self::FIRST_RETRY_MINUTES * (2 ** min($failures - 1, 12)));

        if ($retryAfter !== null) {
            $waitMinutes = max($waitMinutes, (int) ceil($retryAfter / 60));
        }

        $run->update(['status' => 'failed', 'finished_at' => now(), 'error' => $message, 'http_status' => $status, ...$collector->counts]);
        $source->forceFill([
            'status' => $failures >= self::FAILING_AFTER ? 'failing' : 'degraded',
            'consecutive_failures' => $failures,
            'last_failure_at' => now(),
            'last_error' => $message,
            'next_due_at' => now()->addMinutes($waitMinutes),
        ])->save();
    }
}
