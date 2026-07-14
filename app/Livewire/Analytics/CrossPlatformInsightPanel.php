<?php

namespace App\Livewire\Analytics;

use App\Models\CrossPlatformInsight;
use App\Services\CrossPlatformIntelligenceService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Cross-Platform Insight Panel
 *
 * Surfaces insights that require data from multiple Dot platforms to discover.
 * These are insights no single platform — and no traditional BI tool — can see.
 */
class CrossPlatformInsightPanel extends Component
{
    public string $filterType     = '';
    public string $filterSeverity = '';
    public bool   $running        = false;

    #[Computed]
    public function insights(): Collection
    {
        return CrossPlatformInsight::where('team_id', Auth::user()->currentTeam->id)
            ->whereIn('status', ['new', 'reviewed'])
            ->when($this->filterType,     fn ($q) => $q->where('insight_type', $this->filterType))
            ->when($this->filterSeverity, fn ($q) => $q->where('severity', $this->filterSeverity))
            ->orderByRaw("CASE severity WHEN 'critical' THEN 1 WHEN 'warning' THEN 2 ELSE 3 END")
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();
    }

    public function runEngines(): void
    {
        $this->running = true;

        $service = app(CrossPlatformIntelligenceService::class);
        $service->runForTeam(Auth::user()->currentTeam);

        unset($this->insights);
        $this->running = false;
    }

    public function review(int $id): void
    {
        CrossPlatformInsight::where('team_id', Auth::user()->currentTeam->id)
            ->findOrFail($id)
            ->review();
        unset($this->insights);
    }

    public function dismiss(int $id): void
    {
        CrossPlatformInsight::where('team_id', Auth::user()->currentTeam->id)
            ->findOrFail($id)
            ->dismiss();
        unset($this->insights);
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.analytics.cross-platform-insight-panel');
    }
}
