<?php

namespace App\Livewire\Analytics;

use App\Actions\Analytics\GenerateExecutiveBriefingAction;
use App\Models\DataSource;
use App\Models\ExecutiveBriefing;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ExecutiveBriefingPanel extends Component
{
    public string $period = 'weekly';

    public bool $generating = false;

    #[Computed]
    public function briefing(): ?ExecutiveBriefing
    {
        if (! Auth::user()->currentTeam) {
            return null;
        }

        return ExecutiveBriefing::where('team_id', Auth::user()->currentTeam->id)
            ->where('period', $this->period)
            ->orderByDesc('period_date')
            ->first();
    }

    #[Computed]
    public function hasData(): bool
    {
        $team = Auth::user()->currentTeam;

        return $team !== null
            && DataSource::where('team_id', $team->id)->where('status', 'connected')->exists();
    }

    public function generate(): void
    {
        Gate::authorize('generate-briefing');

        $this->generating = true;

        app(GenerateExecutiveBriefingAction::class)
            ->handle(Auth::user()->currentTeam, $this->period);

        // After dispatch, poll until ready (Livewire polling handles this)
        unset($this->briefing);
        $this->generating = false;
    }

    public function render(): View
    {
        return view('livewire.analytics.executive-briefing-panel');
    }
}
