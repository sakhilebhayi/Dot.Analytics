<?php

namespace App\Console\Commands;

use App\Services\BusinessDnaService;
use App\Models\Team;
use Illuminate\Console\Command;

class RecomputeDnaCommand extends Command
{
    protected $signature   = 'analytics:recompute-dna
                                {--team= : Recompute for a specific team ID only}';
    protected $description = 'Recompute Business DNA profiles for all teams';

    public function handle(BusinessDnaService $service): int
    {
        $teamId = $this->option('team');

        $query = Team::query();
        if ($teamId) {
            $query->where('id', $teamId);
        }

        $teams = $query->get();

        foreach ($teams as $team) {
            $profile = $service->computeForTeam($team);
            $this->line("  [{$team->id}] {$team->name}: confidence={$profile->confidence_score}");
        }

        $this->info("DNA recomputed for {$teams->count()} team(s).");
        return self::SUCCESS;
    }
}
