<?php

namespace App\Events\Analytics;

use App\Models\CrossPlatformInsight;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a critical cross-platform insight is discovered.
 * Triggers notifications to relevant team members.
 */
class CriticalInsightDiscovered
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly CrossPlatformInsight $insight,
    ) {}
}
