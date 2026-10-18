<?php

namespace App\Livewire\Analytics;

use App\Models\AnalyticsSnapshot;
use App\Models\BusinessDnaProfile;
use App\Services\BusinessDnaService;
use App\Services\IntelligenceEngineService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Business DNA Panel
 *
 * Displays the evolving operational fingerprint of the organisation.
 * The DNA profile grows more accurate as more platforms connect and
 * more historical data accumulates.
 */
class BusinessDnaPanel extends Component
{
    public bool $computing = false;

    /** Why the last recompute produced nothing; null once there is something to show. */
    public ?string $notice = null;

    #[Computed]
    public function profile(): ?BusinessDnaProfile
    {
        return BusinessDnaProfile::first();
    }

    public function compute(): void
    {
        Gate::authorize('run-intelligence-engines');

        $this->computing = true;

        $team = Auth::user()->currentTeam;
        $before = BusinessDnaProfile::first()?->last_computed_at?->getTimestamp();
        $profile = app(BusinessDnaService::class)->computeForTeam($team);
        $unchanged = $profile === null || $profile->last_computed_at?->getTimestamp() === $before;

        $this->notice = match (true) {
            ! $unchanged => null,
            ! $this->hasSnapshots() => 'Nothing to compute: no data yet. A connected platform has to send its first snapshot.',
            default => 'The profile could not be computed: no AI provider is configured, or it returned nothing usable. Nothing was changed.',
        };

        unset($this->profile);
        $this->computing = false;
    }

    private function hasSnapshots(): bool
    {
        return AnalyticsSnapshot::whereHas('dataSource', fn ($q) => $q->where('status', 'connected')
            ->whereIn('platform', array_keys(IntelligenceEngineService::PLATFORMS)))->exists();
    }

    public function render(): View
    {
        return view('livewire.analytics.business-dna-panel');
    }
}
