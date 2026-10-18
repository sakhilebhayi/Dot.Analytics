<?php

namespace App\Listeners\Analytics;

use App\Events\Analytics\CriticalInsightDiscovered;
use App\Models\AnalyticsAlert;
use App\Services\Notifications\TeamNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * When a critical cross-platform insight is discovered, create a high-severity alert so it
 * surfaces in the UI immediately, then tell the team (in-app bell, mail, real-time push).
 *
 * The alert is keyed on its insight, so a retried listener (queue retry, duplicate
 * dispatch) neither duplicates the alert nor notifies anyone twice.
 */
class NotifyOnCriticalInsight implements ShouldQueue
{
    public function __construct(private readonly ?TeamNotifier $notifier = null) {}

    public function handle(CriticalInsightDiscovered $event): void
    {
        $insight = $event->insight;

        try {
            $alert = AnalyticsAlert::withoutGlobalScopes()->firstOrCreate(
                ['team_id' => $insight->team_id, 'dedupe_key' => "insight:{$insight->id}"],
                [
                    'title' => $insight->title,
                    'description' => $insight->narrative,
                    'severity' => 'critical',
                    'status' => 'open',
                    'context' => [
                        'platforms_involved' => $insight->platforms_involved,
                        'insight_type' => $insight->insight_type,
                        'confidence' => $insight->confidence,
                        'source' => 'cross_platform_intelligence',
                        'insight_id' => $insight->id,
                    ],
                    'triggered_at' => now(),
                ],
            );
        } catch (UniqueConstraintViolationException) {
            return; // another delivery of the same event created it first
        }

        if ($alert->wasRecentlyCreated) {
            ($this->notifier ?? app(TeamNotifier::class))->criticalAlert($alert);
        }
    }
}
