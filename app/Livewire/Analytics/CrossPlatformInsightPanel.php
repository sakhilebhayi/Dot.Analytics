<?php

namespace App\Livewire\Analytics;

use App\Models\CrossPlatformInsight;
use App\Models\DataSource;
use App\Models\IntelligenceEngineRun;
use App\Services\CrossPlatformIntelligenceService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
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
    public string $filterType = '';

    public string $filterSeverity = '';

    public bool $running = false;

    /** Plain-language outcome of the last "Run Engines" click (skipped / no data / failed reasons). */
    public ?string $notice = null;

    #[Computed]
    public function hasData(): bool
    {
        $team = Auth::user()->currentTeam;

        return $team !== null
            && DataSource::where('team_id', $team->id)->where('status', 'connected')->exists();
    }

    /** Most recent run of each engine that did not produce output, with the reason why. */
    #[Computed]
    public function unproductiveRuns(): Collection
    {
        return IntelligenceEngineRun::whereIn('status', ['skipped', 'no_data', 'failed'])
            ->whereNotNull('status_reason')
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->unique('engine')
            ->take(3)
            ->values();
    }

    #[Computed]
    public function insights(): Collection
    {
        return CrossPlatformInsight::whereIn('status', ['new', 'reviewed'])
            ->when($this->filterType, fn ($q) => $q->where('insight_type', $this->filterType))
            ->when($this->filterSeverity, fn ($q) => $q->where('severity', $this->filterSeverity))
            ->orderByRaw("CASE severity WHEN 'critical' THEN 1 WHEN 'warning' THEN 2 ELSE 3 END")
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();
    }

    public function runEngines(): void
    {
        Gate::authorize('run-intelligence-engines');

        $this->running = true;

        $runs = app(CrossPlatformIntelligenceService::class)->runAllForTeam(Auth::user()->currentTeam);

        $made = array_sum(array_map(fn ($r) => $r->insights_generated, $runs));
        $blocked = array_values(array_filter($runs, fn ($r) => in_array($r->status, ['skipped', 'no_data', 'failed'], true)));

        $this->notice = match (true) {
            $runs === [] => 'No data connected yet. Connect a platform first.',
            $blocked !== [] && $made === 0 => $blocked[0]->status_reason,
            default => $made === 0
                ? 'Engines ran but found no pattern the data supports.'
                : "{$made} new insight(s) from your ingested data.",
        };

        unset($this->insights, $this->unproductiveRuns);
        $this->running = false;
    }

    public function review(int $id): void
    {
        $insight = CrossPlatformInsight::findOrFail($id);
        Gate::authorize('update', $insight);

        $insight->review();
        unset($this->insights);
    }

    public function dismiss(int $id): void
    {
        $insight = CrossPlatformInsight::findOrFail($id);
        Gate::authorize('update', $insight);

        $insight->dismiss();
        unset($this->insights);
    }

    public function render(): View
    {
        return view('livewire.analytics.cross-platform-insight-panel');
    }
}
