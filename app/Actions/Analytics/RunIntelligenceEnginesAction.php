<?php

namespace App\Actions\Analytics;

use App\Models\Team;
use App\Services\IntelligenceEngineService;
use App\Jobs\Analytics\RunIntelligenceEngineJob;

/**
 * Dispatches all applicable intelligence engine jobs for a team.
 * Can be triggered manually, from a schedule, or after major data changes.
 *
 * Returns the number of engines dispatched.
 */
class RunIntelligenceEnginesAction
{
    public function __construct(
        private readonly IntelligenceEngineService $engineService,
    ) {}

    public function handle(Team $team, ?array $engineKeys = null): int
    {
        $connected = $team->dataSources()
            ->where('status', 'connected')
            ->pluck('platform')
            ->toArray();

        if (empty($connected)) {
            return 0;
        }

        $activeEngines = $this->engineService->getActiveEngines($connected);

        // If specific engines requested, filter to those
        if ($engineKeys !== null) {
            $activeEngines = array_intersect_key($activeEngines, array_flip($engineKeys));
        }

        $dispatched = 0;
        $delay      = 0;

        foreach (array_keys($activeEngines) as $engineKey) {
            RunIntelligenceEngineJob::dispatch($team->id, $engineKey)
                ->delay(now()->addSeconds($delay));
            $delay += 5;
            $dispatched++;
        }

        return $dispatched;
    }
}
