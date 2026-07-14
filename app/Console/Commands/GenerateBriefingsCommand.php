<?php

namespace App\Console\Commands;

use App\Actions\Analytics\GenerateExecutiveBriefingAction;
use App\Models\Team;
use Illuminate\Console\Command;

class GenerateBriefingsCommand extends Command
{
    protected $signature   = 'analytics:briefings
                                {period=weekly : Briefing period — daily, weekly, or monthly}
                                {--team= : Generate for a specific team ID only}
                                {--date= : Period date (YYYY-MM-DD), defaults to today}';
    protected $description = 'Queue executive intelligence briefings for all teams';

    public function handle(GenerateExecutiveBriefingAction $action): int
    {
        $period = $this->argument('period');
        $date   = $this->option('date') ?? now()->toDateString();
        $teamId = $this->option('team');

        if (! in_array($period, ['daily', 'weekly', 'monthly'])) {
            $this->error("Invalid period '{$period}'. Use: daily, weekly, monthly");
            return self::FAILURE;
        }

        $query = Team::query();
        if ($teamId) {
            $query->where('id', $teamId);
        }

        $teams = $query->get();

        foreach ($teams as $team) {
            $action->handle($team, $period, $date);
            $this->line("  Queued {$period} briefing for [{$team->id}] {$team->name}");
        }

        $this->info("Briefings queued for {$teams->count()} team(s).");
        return self::SUCCESS;
    }
}
