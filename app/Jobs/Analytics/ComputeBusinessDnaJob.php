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

    public int $tries   = 2;
    public int $timeout = 60;

    public function __construct(public readonly int $teamId) {}

    public function handle(BusinessDnaService $service): void
    {
        $team = Team::find($this->teamId);
        if (! $team) {
            return;
        }

        $previous = $team->businessDna?->confidence_score ?? 0.0;

        $profile = $service->computeForTeam($team);

        BusinessDnaRecomputed::dispatch(
            $this->teamId,
            $profile->confidence_score,
            $previous,
        );
    }
}
