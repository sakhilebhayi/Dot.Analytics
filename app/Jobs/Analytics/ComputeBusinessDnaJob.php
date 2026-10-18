<?php

namespace App\Jobs\Analytics;

use App\Events\Analytics\BusinessDnaRecomputed;
use App\Models\Team;
use App\Services\BusinessDnaService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ComputeBusinessDnaJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 60;

    public function __construct(public readonly int $teamId) {}

    public function handle(BusinessDnaService $service): void
    {
        $team = Team::find($this->teamId);
        if (! $team) {
            return;
        }

        $existing = $team->businessDna;
        $previous = $existing?->confidence_score ?? 0.0;
        $computedBefore = $existing?->last_computed_at?->getTimestamp();

        $profile = $service->computeForTeam($team);

        // No profile, or the existing one came back untouched (no data, no provider,
        // an unusable answer): nothing was recomputed, so nothing is announced.
        if ($profile === null || $profile->last_computed_at?->getTimestamp() === $computedBefore) {
            return;
        }

        BusinessDnaRecomputed::dispatch(
            $this->teamId,
            $profile->confidence_score,
            $previous,
        );
    }
}
