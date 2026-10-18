<?php

namespace App\Jobs\Analytics;

use App\Models\Team;
use App\Services\Metrics\ComputeMetrics;
use App\Services\Metrics\MetricAnomalyDetector;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Refreshes one team's computed metrics and anomaly alerts right after a new snapshot arrives, so a
 * tile does not wait for the hourly run. Safe to run any number of times: the
 * computation is idempotent.
 */
class ComputeMetricsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 60;

    public function __construct(public readonly int $teamId) {}

    public function handle(ComputeMetrics $compute, MetricAnomalyDetector $detector): void
    {
        $team = Team::find($this->teamId);

        if ($team) {
            $compute->forTeam($team);
            $detector->detectForTeam($team);
        }
    }
}
