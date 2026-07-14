<?php

namespace App\Livewire\Analytics;

use App\Actions\Analytics\GenerateExecutiveBriefingAction;
use App\Models\ExecutiveBriefing;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ExecutiveBriefingPanel extends Component
{
    public string $period       = 'weekly';
    public bool   $generating   = false;

    #[Computed]
    public function briefing(): ?ExecutiveBriefing
    {
        return ExecutiveBriefing::where('team_id', Auth::user()->currentTeam->id)
            ->where('period', $this->period)
            ->orderByDesc('period_date')
            ->first();
    }

    public function generate(): void
    {
        $this->generating = true;

        app(GenerateExecutiveBriefingAction::class)
            ->handle(Auth::user()->currentTeam, $this->period);

        // After dispatch, poll until ready (Livewire polling handles this)
        unset($this->briefing);
        $this->generating = false;
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.analytics.executive-briefing-panel');
    }
}
