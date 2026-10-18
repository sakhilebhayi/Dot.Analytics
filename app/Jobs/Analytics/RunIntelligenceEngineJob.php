<?php

namespace App\Jobs\Analytics;

use App\Models\Team;
use App\Services\CrossPlatformIntelligenceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Runs a single intelligence engine for a team asynchronously.
 * Dispatched when a platform connects, on a schedule, or manually.
 *
 * All generation rules (real snapshots only, no canned text, provenance) live in
 * CrossPlatformIntelligenceService so every entry point behaves identically.
 */
class RunIntelligenceEngineJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public readonly int $teamId,
        public readonly string $engine,
    ) {}

    public function handle(CrossPlatformIntelligenceService $service): void
    {
        $team = Team::find($this->teamId);
        if (! $team) {
            return;
        }

        $service->runEngine($team, $this->engine);
    }
}
