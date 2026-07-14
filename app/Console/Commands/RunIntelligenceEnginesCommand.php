<?php

namespace App\Console\Commands;

use App\Actions\Analytics\RunIntelligenceEnginesAction;
use App\Models\Team;
use Illuminate\Console\Command;

class RunIntelligenceEnginesCommand extends Command
{
    protected $signature   = 'analytics:run-engines
                                {--team= : Run for a specific team ID only}
                                {--engine= : Run a specific engine only}';
    protected $description = 'Dispatch intelligence engine jobs for all connected teams';

    public function handle(RunIntelligenceEnginesAction $action): int
    {
        $teamId  = $this->option('team');
        $engine  = $this->option('engine');

        $query = Team::query();
        if ($teamId) {
            $query->where('id', $teamId);
        }

        $teams       = $query->get();
        $dispatched  = 0;

        foreach ($teams as $team) {
            $count = $action->handle($team, $engine ? [$engine] : null);
            $dispatched += $count;
            $this->line("  Team [{$team->id}] {$team->name}: dispatched {$count} engine(s)");
        }

        $this->info("Total engines dispatched: {$dispatched}");
        return self::SUCCESS;
    }
}
