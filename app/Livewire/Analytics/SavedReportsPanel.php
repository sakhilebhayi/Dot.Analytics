<?php

namespace App\Livewire\Analytics;

use App\Models\AnalyticsReport;
use App\Models\ReportRun;
use App\Services\ReportGenerationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class SavedReportsPanel extends Component
{
    public bool   $showCreate  = false;
    public string $title       = '';
    public string $reportType  = 'insights';
    public string $description = '';
    public bool   $running     = false;
    public ?int   $runningId   = null;

    protected array $rules = [
        'title'      => 'required|string|max:120',
        'reportType' => 'required|in:insights,alerts,recommendations,metrics',
        'description' => 'nullable|string|max:500',
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
        $this->validate();

        AnalyticsReport::create([
            'team_id'     => Auth::user()->currentTeam->id,
            'user_id'     => Auth::id(),
            'title'       => $this->title,
            'description' => $this->description ?: null,
            'type'        => 'ad_hoc',
            'config'      => ['report_type' => $this->reportType],
        ]);

        $this->reset(['title', 'description', 'showCreate']);
        unset($this->reports);
    }

    public function run(int $id): void
    {
        $this->running   = true;
        $this->runningId = $id;

        $team   = Auth::user()->currentTeam;
        $report = AnalyticsReport::findOrFail($id);

        $run = ReportRun::create([
            'analytics_report_id' => $report->id,
            'status'              => 'running',
            'started_at'          => now(),
        ]);

        try {
            $output = app(ReportGenerationService::class)->generateJson(
                $team,
                $report->config['report_type'] ?? 'insights',
            );

            $run->update(['status' => 'completed', 'output' => $output, 'completed_at' => now()]);
        } catch (\Throwable) {
            $run->update(['status' => 'failed', 'completed_at' => now()]);
        }

        unset($this->reports);
        $this->running   = false;
        $this->runningId = null;
    }

    public function delete(int $id): void
    {
        AnalyticsReport::findOrFail($id)->delete();

        unset($this->reports);
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.analytics.saved-reports-panel');
    }
}
