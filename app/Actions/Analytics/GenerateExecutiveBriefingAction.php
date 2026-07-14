<?php

namespace App\Actions\Analytics;

use App\Jobs\Analytics\GenerateExecutiveBriefingJob;
use App\Models\Team;

/**
 * Queues an executive intelligence briefing for the given team and period.
 */
class GenerateExecutiveBriefingAction
{
    public function handle(Team $team, string $period = 'weekly', ?string $periodDate = null): void
    {
        GenerateExecutiveBriefingJob::dispatch(
            $team->id,
            $period,
            $periodDate ?? now()->toDateString(),
        );
    }
}
