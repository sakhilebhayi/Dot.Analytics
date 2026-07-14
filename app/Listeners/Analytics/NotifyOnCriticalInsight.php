<?php

namespace App\Listeners\Analytics;

use App\Events\Analytics\CriticalInsightDiscovered;
use App\Models\AnalyticsAlert;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * When a critical cross-platform insight is discovered, automatically
 * create a high-severity alert so it surfaces in the UI immediately.
 */
class NotifyOnCriticalInsight implements ShouldQueue
{
    public function handle(CriticalInsightDiscovered $event): void
    {
        $insight = $event->insight;

        AnalyticsAlert::create([
            'team_id'     => $insight->team_id,
            'title'       => $insight->title,
            'description' => $insight->narrative,
            'severity'    => 'critical',
            'status'      => 'open',
            'context'     => [
                'platforms_involved' => $insight->platforms_involved,
                'insight_type'       => $insight->insight_type,
                'confidence'         => $insight->confidence,
                'source'             => 'cross_platform_intelligence',
                'insight_id'         => $insight->id,
            ],
            'triggered_at' => now(),
        ]);
    }
}
