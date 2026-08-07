<?php

namespace App\Livewire\Analytics;

use App\Models\BusinessDnaProfile;
use App\Services\BusinessDnaService;
use Illuminate\Support\Facades\Auth;
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

    #[Computed]
    public function profile(): ?BusinessDnaProfile
    {
        return BusinessDnaProfile::first();
    }

    public function compute(): void
    {
        $this->computing = true;

        $service = app(BusinessDnaService::class);
        $service->computeForTeam(Auth::user()->currentTeam);

        unset($this->profile);
        $this->computing = false;
    }

    public function render(): View
    {
        return view('livewire.analytics.business-dna-panel');
    }
}
