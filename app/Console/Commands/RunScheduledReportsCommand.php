<?php

namespace App\Console\Commands;

use App\Models\AnalyticsReport;
use App\Models\ReportRun;
use App\Services\ReportGenerationService;
use Cron\CronExpression;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Runs every saved report whose cron_expression is due. Scheduled every minute.
 *
 * The expression is read in the report's team timezone. A report runs at most
 * once per minute however often this fires, an invalid stored expression is
 * skipped (and logged) rather than stopping the rest, and each run is recorded
 * in report_runs like a manual run. Nothing is delivered anywhere: the output
 * is the run history.
 */
class RunScheduledReportsCommand extends Command
{
    protected $signature = 'analytics:run-scheduled-reports';

    protected $description = 'Run saved reports whose cron expression is due (scheduled every minute)';

    public function handle(ReportGenerationService $reports): int
    {
        $now = Carbon::now();
        $ran = 0;

        AnalyticsReport::withoutGlobalScopes()
            ->whereNotNull('cron_expression')
            ->where('cron_expression', '!=', '')
            ->with('team')
            ->orderBy('id')
            ->chunkById(100, function ($chunk) use ($reports, $now, &$ran) {
                foreach ($chunk as $report) {
                    if ($this->isDue($report, $now) && $this->runReport($report, $reports, $now)) {
                        $ran++;
                    }
                }
            });

        $this->info("Scheduled reports run: {$ran}.");

        return self::SUCCESS;
    }

    private function isDue(AnalyticsReport $report, Carbon $now): bool
    {
        $expression = trim($report->cron_expression);

        if (! CronExpression::isValidExpression($expression)) {
            Log::warning('Scheduled report skipped: invalid cron expression', ['report_id' => $report->id]);

            return false;
        }

        $timezone = $report->team?->timezone;
        if (! is_string($timezone) || ! in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
            $timezone = 'UTC';
        }

        if (! (new CronExpression($expression))->isDue($now, $timezone)) {
            return false;
        }

        return ! ReportRun::where('analytics_report_id', $report->id)
            ->where('started_at', '>=', $now->copy()->startOfMinute())
            ->exists();
    }

    private function runReport(AnalyticsReport $report, ReportGenerationService $reports, Carbon $now): bool
    {
        $run = ReportRun::create([
            'analytics_report_id' => $report->id,
            'status' => 'running',
            'started_at' => $now,
        ]);

        $type = $report->reportType();
        if ($type === null || ! $report->team) {
            $run->update([
                'status' => 'failed',
                'output' => ['error' => "Unknown report type '".($report->config['report_type'] ?? $report->type)."'."],
                'completed_at' => Carbon::now(),
            ]);

            return true;
        }

        try {
            $output = $reports->generateJson($report->team, $type, $report->config ?? []);
            $run->update(['status' => 'completed', 'output' => $output, 'completed_at' => Carbon::now()]);
        } catch (\Throwable $e) {
            report($e);
            $run->update(['status' => 'failed', 'output' => ['error' => 'Report generation failed.'], 'completed_at' => Carbon::now()]);
        }

        return true;
    }
}
