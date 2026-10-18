<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Services\Metrics\ComputeMetrics;
use Illuminate\Console\Command;

class ComputeMetricsCommand extends Command
{
    protected $signature = 'analytics:compute-metrics
                                {--team= : Compute for a specific team ID only}';

    protected $description = 'Turn the latest platform snapshots into computed metrics (idempotent; scheduled hourly)';

    public function handle(ComputeMetrics $compute): int
    {
        $query = Team::query()->orderBy('id');
        if ($teamId = $this->option('team')) {
            $query->where('id', $teamId);
        }

        $teams = $query->get();

        foreach ($teams as $team) {
            $summary = $compute->forTeam($team);

            $this->line(sprintf(
                '  [%d] %s: %d platform(s), %d point(s) seen, %d written, %d stale',
                $team->id,
                $team->name,
                $summary['platforms'],
                $summary['metrics_seen'],
                $summary['metrics_written'],
                $summary['stale_metrics'],
            ));
        }

        $this->info("Metrics computed for {$teams->count()} team(s).");

        return self::SUCCESS;
    }
}
