<?php

namespace App\Listeners\Analytics;

use App\Events\Analytics\PlatformConnected;
use App\Jobs\Analytics\ComputeBusinessDnaJob;
use App\Jobs\Analytics\RunIntelligenceEngineJob;
use App\Models\Team;
use App\Services\IntelligenceEngineService;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * When a new platform is connected, automatically dispatch:
 * 1. Intelligence engine jobs for every engine that now has
 *    at least one of its source platforms satisfied.
 * 2. A Business DNA recompute job to incorporate the new platform.
 */
class DispatchEnginesOnPlatformConnection implements ShouldQueue
{
    public function __construct(
        private readonly IntelligenceEngineService $engineService,
    ) {}

    public function handle(PlatformConnected $event): void
    {
        $team = Team::find($event->teamId);
        if (! $team) {
            return;
        }

        $connectedPlatforms = $team->dataSources()
            ->where('status', 'connected')
            ->pluck('platform')
            ->toArray();

        $activeEngines = $this->engineService->getActiveEngines($connectedPlatforms);

        // Dispatch each relevant engine with a short delay to spread load
        $delay = 0;
        foreach (array_keys($activeEngines) as $engineKey) {
            RunIntelligenceEngineJob::dispatch($event->teamId, $engineKey)
                ->delay(now()->addSeconds($delay));
            $delay += 3;
        }

        // Recompute Business DNA now that there is a new platform
        ComputeBusinessDnaJob::dispatch($event->teamId)
            ->delay(now()->addSeconds($delay));
    }
}
