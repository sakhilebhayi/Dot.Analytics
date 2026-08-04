<?php

namespace App\Livewire\Analytics;

use App\Models\AnalyticsAlert;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class AlertsPanel extends Component
{
    public string $filterSeverity = '';
    public string $filterStatus = 'open';

    #[Computed]
    public function alerts(): Collection
    {
        return AnalyticsAlert::when($this->filterSeverity, fn ($q) => $q->where('severity', $this->filterSeverity))
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->orderByRaw("CASE severity WHEN 'critical' THEN 1 WHEN 'warning' THEN 2 ELSE 3 END")
            ->orderByDesc('triggered_at')
            ->get();
    }

    public function acknowledge(int $id): void
    {
        AnalyticsAlert::findOrFail($id)->update(['status' => 'acknowledged']);
        unset($this->alerts);
    }

    public function resolve(int $id): void
    {
        AnalyticsAlert::findOrFail($id)->update(['status' => 'resolved', 'resolved_at' => now()]);
        unset($this->alerts);
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.analytics.alerts-panel');
    }
}
