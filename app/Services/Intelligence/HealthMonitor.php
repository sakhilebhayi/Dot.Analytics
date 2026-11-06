<?php

namespace App\Services\Intelligence;

use App\Models\Intelligence\DataIssue;
use App\Models\Intelligence\Indicator;
use App\Models\Intelligence\Observation;
use App\Models\Intelligence\Source;
use App\Services\Intelligence\Fetchers\DataIssues;
use Illuminate\Support\Carbon;

/** Looks for information that has gone quiet or wrong, and keeps one open issue for each thing found. */
class HealthMonitor
{
    public function __construct(private readonly DataIssues $issues) {}

    public function check(): int
    {
        $before = DataIssue::whereNull('resolved_at')->count();
        $seen = [];

        foreach (Source::where('is_enabled', true)->get() as $source) {
            if ($source->status === 'failing') {
                $seen[] = $this->raise('feed', 'high', "{$source->name} has failed {$source->consecutive_failures} times in a row: {$source->last_error}");
            } elseif ($source->last_success_at !== null && $source->isStale()) {
                $seen[] = $this->raise('stale', 'medium', "{$source->name} has not been collected for over {$source->stale_after_hours} hours.");
            }
        }

        foreach (Indicator::where('is_published', true)->get() as $indicator) {
            $latest = Observation::where('indicator_id', $indicator->id)->max('period');

            if ($latest === null) {
                if (Source::whereKey($indicator->source_id)->value('last_success_at') !== null) {
                    $seen[] = $this->raise('missing', 'medium', "{$indicator->name} has no figures from a source that has been collected.");
                }
            } elseif (Carbon::parse($latest)->diffInDays(now()) > $indicator->stale_after_days) {
                $seen[] = $this->raise('stale', 'low', "The newest figure for {$indicator->name} is from {$latest}.");
            }
        }

        // An issue not seen this time has cleared.
        DataIssue::whereNull('resolved_at')->whereNotIn('id', $seen)->whereIn('kind', ['feed', 'stale', 'missing'])->update(['resolved_at' => now()]);

        return max(0, DataIssue::whereNull('resolved_at')->count() - $before);
    }

    private function raise(string $kind, string $severity, string $summary): int
    {
        return $this->issues->raise($kind, $severity, $summary)->id;
    }
}
