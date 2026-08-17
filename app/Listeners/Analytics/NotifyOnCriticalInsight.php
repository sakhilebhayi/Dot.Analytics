<?php

namespace App\Listeners\Analytics;

use App\Events\Analytics\CriticalInsightDiscovered;
use App\Models\AnalyticsAlert;
use App\Notifications\CriticalAlertTriggered;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/**
 * When a critical cross-platform insight is discovered, create a
 * high-severity alert so it surfaces in the UI immediately, and notify
 * every team member (mail + in-app bell + real-time broadcast).
 */
class NotifyOnCriticalInsight implements ShouldQueue
{
    public function handle(CriticalInsightDiscovered $event): void
    {
        $insight = $event->insight;

        $alert = AnalyticsAlert::create([
            'team_id' => $insight->team_id,
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
        ]);

        Notification::send($insight->team->allUsers(), new CriticalAlertTriggered($alert));
    }
}
