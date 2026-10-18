<?php

namespace App\Livewire\Analytics;

use App\Models\AnalyticsReport;
use App\Models\ReportRun;
use App\Services\ReportGenerationService;
use Cron\CronExpression;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class SavedReportsPanel extends Component
{
    public bool $showCreate = false;

    public string $title = '';

    public string $reportType = 'insights';

    public string $description = '';

    /** Optional cron expression (five fields, read in the team's timezone). Blank = run on demand only. */
    public string $schedule = '';

    public bool $running = false;

    public ?int $runningId = null;

    protected array $rules = [
        'title' => 'required|string|max:120',
        'reportType' => 'required|in:insights,alerts,recommendations,metrics',
        'description' => 'nullable|string|max:500',
        'schedule' => 'nullable|string|max:100',
    ];

    #[Computed]
    public function reports(): Collection
    {
        return AnalyticsReport::with('latestRun')
            ->orderByDesc('created_at')
            ->get();
    }

    public function create(): void
    {
        Gate::authorize('manage-reports');

        $this->validate();

        $schedule = trim($this->schedule);
        if ($schedule !== '' && ! CronExpression::isValidExpression($schedule)) {
            $this->addError('schedule', 'That is not a valid cron expression (five fields, e.g. 0 6 * * 1).');

            return;
        }

        if (! Auth::user()->currentTeam) {
            $this->addError('title', 'You need a team before creating reports.');

            return;
        }

        AnalyticsReport::create([
            'team_id' => Auth::user()->currentTeam->id,
            'user_id' => Auth::id(),
            'title' => $this->title,
            'description' => $this->description ?: null,
            'type' => $schedule === '' ? 'ad_hoc' : 'scheduled',
            'cron_expression' => $schedule === '' ? null : $schedule,
            'config' => ['report_type' => $this->reportType],
        ]);

        $this->reset(['title', 'description', 'schedule', 'showCreate']);
        unset($this->reports);
    }

    public function run(int $id): void
    {
        Gate::authorize('run-reports');

        $this->running = true;
        $this->runningId = $id;

        $team = Auth::user()->currentTeam;
        $report = AnalyticsReport::findOrFail($id);

        $run = ReportRun::create([
            'analytics_report_id' => $report->id,
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $output = app(ReportGenerationService::class)->generateJson(
                $team,
                $report->reportType() ?? 'insights',
            );

            $run->update(['status' => 'completed', 'output' => $output, 'completed_at' => now()]);
        } catch (\Throwable) {
            $run->update(['status' => 'failed', 'completed_at' => now()]);
        }

        unset($this->reports);
        $this->running = false;
        $this->runningId = null;
    }

    public function delete(int $id): void
    {
        Gate::authorize('manage-reports');

        AnalyticsReport::findOrFail($id)->delete();

        unset($this->reports);
    }

    public function render(): View
    {
        return view('livewire.analytics.saved-reports-panel');
    }
}
