<?php

use App\Console\Commands\GenerateBriefingsCommand;
use App\Console\Commands\RecomputeDnaCommand;
use App\Console\Commands\RunIntelligenceEnginesCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ─── Intelligence Engine Schedule ─────────────────────────────────────────────
// Run all intelligence engines for all teams every 6 hours
Schedule::command(RunIntelligenceEnginesCommand::class)->everySixHours()
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Run intelligence engines across all teams');

// Generate daily briefings at 06:00 every morning
Schedule::command(GenerateBriefingsCommand::class, ['daily'])
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Generate daily executive intelligence briefings');

// Generate weekly briefings on Monday mornings
Schedule::command(GenerateBriefingsCommand::class, ['weekly'])
    ->weeklyOn(1, '06:30')
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Generate weekly executive intelligence briefings');

// Generate monthly briefings on the 1st of each month
Schedule::command(GenerateBriefingsCommand::class, ['monthly'])
    ->monthlyOn(1, '07:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Generate monthly executive intelligence briefings');

// Recompute Business DNA overnight for all teams
Schedule::command(RecomputeDnaCommand::class)
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Recompute Business DNA profiles');

