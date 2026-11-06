<?php

use App\Console\Commands\ComputeMetricsCommand;
use App\Console\Commands\GenerateBriefingsCommand;
use App\Console\Commands\RecomputeDnaCommand;
use App\Console\Commands\RunIntelligenceEnginesCommand;
use App\Console\Commands\RunScheduledReportsCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ─── Metric computation ───────────────────────────────────────────────────────
// Turn the latest platform snapshots into computed metrics (idempotent). Also
// run right after each new snapshot arrives; the hourly run is the safety net.
Schedule::command(ComputeMetricsCommand::class)->hourly()
    ->withoutOverlapping(30)
    ->onOneServer()
    ->description('Compute metrics from platform snapshots');

// ─── Intelligence Engine Schedule ─────────────────────────────────────────────
// Run all intelligence engines for all teams every 6 hours
Schedule::command(RunIntelligenceEnginesCommand::class)->everySixHours()
    ->withoutOverlapping(30)
    ->onOneServer()
    ->description('Run intelligence engines across all teams');

// Generate daily briefings at 06:00 every morning
Schedule::command(GenerateBriefingsCommand::class, ['daily'])
    ->dailyAt('06:00')
    ->withoutOverlapping(30)
    ->onOneServer()
    ->description('Generate daily executive intelligence briefings');

// Generate weekly briefings on Monday mornings
Schedule::command(GenerateBriefingsCommand::class, ['weekly'])
    ->weeklyOn(1, '06:30')
    ->withoutOverlapping(30)
    ->onOneServer()
    ->description('Generate weekly executive intelligence briefings');

// Generate monthly briefings on the 1st of each month
Schedule::command(GenerateBriefingsCommand::class, ['monthly'])
    ->monthlyOn(1, '07:00')
    ->withoutOverlapping(30)
    ->onOneServer()
    ->description('Generate monthly executive intelligence briefings');

// Recompute Business DNA overnight for all teams
Schedule::command(RecomputeDnaCommand::class)
    ->dailyAt('02:00')
    ->withoutOverlapping(30)
    ->onOneServer()
    ->description('Recompute Business DNA profiles');

// Run saved reports whose cron_expression is due. Every minute, so a schedule is
// honoured to the minute; the command itself runs a report at most once per minute.
Schedule::command(RunScheduledReportsCommand::class)->everyMinute()
    ->withoutOverlapping(30)
    ->onOneServer()
    ->description('Run due scheduled reports');

// Public intelligence: a short pass every ten minutes. Each pass starts a few due sources and stops cleanly in time for
// the host's short-lived worker, carrying on from where it stopped on the next pass.
Schedule::command('intelligence:collect')->everyTenMinutes()
    ->withoutOverlapping(15)
    ->name('intelligence-collect');

// The library's personal side: record what touches what people watch, then email the digests people asked for. Mornings in
// South Africa are 05:00 UTC plus two hours.
Schedule::command('library:scan')->everyThirtyMinutes()->withoutOverlapping(25)->name('library-scan');
Schedule::command('library:digest daily')->dailyAt('05:00')->withoutOverlapping(30)->name('library-digest-daily');
Schedule::command('library:digest weekly')->weeklyOn(1, '05:00')->withoutOverlapping(30)->name('library-digest-weekly');
