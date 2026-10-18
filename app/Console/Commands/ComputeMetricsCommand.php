<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Services\Metrics\ComputeMetrics;
use App\Services\Metrics\MetricAnomalyDetector;
use Illuminate\Console\Command;

class ComputeMetricsCommand extends Command
{
    protected $signature = 'analytics:compute-metrics
                                {--team= : Compute for a specific team ID only}';

    protected $description = 'Turn the latest platform snapshots into computed metrics, then flag anomalies (idempotent; scheduled hourly)';

    public function handle(ComputeMetrics $compute, MetricAnomalyDetector $detector): int
    {
        $query = Team::query()->orderBy('id');
        if ($teamId = $this->option('team')) {
            $query->where('id', $teamId);
        }

        $teams = $query->get();
        $raised = 0;

        foreach ($teams as $team) {
            $summary = $compute->forTeam($team);
            $anomalies = $detector->detectForTeam($team);
            $raised += $anomalies['created'];

            $this->line(sprintf(
                '  [%d] %s: %d platform(s), %d point(s) seen, %d written, %d stale, %d new alert(s)',
                $team->id,
                $team->name,
                $summary['platforms'],
                $summary['metrics_seen'],
                $summary['metrics_written'],
                $summary['stale_metrics'],
                $anomalies['created'],
            ));
        }

        $this->info("Metrics computed for {$teams->count()} team(s); {$raised} alert(s) raised.");

        return self::SUCCESS;
    }
}
