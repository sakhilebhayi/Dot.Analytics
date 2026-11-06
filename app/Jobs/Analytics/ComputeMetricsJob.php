<?php

namespace App\Jobs\Analytics;

use App\Models\Team;
use App\Services\Metrics\ComputeMetrics;
use App\Services\Metrics\MetricAnomalyDetector;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Refreshes one team's computed metrics and anomaly alerts right after a new snapshot arrives, so a
 * tile does not wait for the hourly run. Safe to run any number of times: the
 * computation is idempotent.
 */
class ComputeMetricsJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 60;

    /** A burst of pushes for one company queues one recompute, not one per snapshot. */
    public int $uniqueFor = 120;

    public function __construct(public readonly int $teamId) {}

    public function uniqueId(): string
    {
        return (string) $this->teamId;
    }

    public function handle(ComputeMetrics $compute, MetricAnomalyDetector $detector): void
    {
        $team = Team::find($this->teamId);

        if ($team) {
            $compute->forTeam($team);
            $detector->detectForTeam($team);
        }
    }
}
