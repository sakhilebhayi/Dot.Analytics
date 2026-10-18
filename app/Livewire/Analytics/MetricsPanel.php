<?php

namespace App\Livewire\Analytics;

use App\Models\Team;
use App\Services\Metrics\MetricCatalogue;
use App\Services\Metrics\MetricReader;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Platform Metrics: the real numbers each connected Dot platform reports, as
 * KPI tiles (value, change since the previous point, sparkline from the stored
 * points), and a trend for the selected metric.
 *
 * Reads only computed_metrics through MetricReader. A metric nobody reported is
 * "no data yet", an old one is "stale", and when nothing is connected the panel
 * explains how to connect a platform and shows the contract instead of numbers.
 * Everyone on the team can read it; there is nothing to change here.
 */
class MetricsPanel extends Component
{
    public const RANGES = [14, 30, 60, 90];

    public string $selectedKey = '';

    public int $days = 30;

    /** Opens on a metric with an open anomaly alert if there is one, else the first with data. */
    public function mount(): void
    {
        $anomalous = $this->dashboard['summary']['anomaly_keys'][0] ?? null;
        if ($anomalous !== null && $this->isSelectable($anomalous)) {
            $this->selectedKey = $anomalous;

            return;
        }

        foreach ($this->dashboard['platforms'] as $platform) {
            foreach ($platform['tiles'] as $tile) {
                if ($tile['state'] !== 'no_data') {
                    $this->selectedKey = $tile['key'];

                    return;
                }
            }
        }
    }

    #[Computed]
    public function dashboard(): array
    {
        $team = $this->team();

        return $team ? app(MetricReader::class)->dashboard($team) : app(MetricReader::class)->empty();
    }

    #[Computed]
    public function series(): ?array
    {
        $team = $this->team();

        return $team && $this->selectedKey !== ''
            ? app(MetricReader::class)->series($team, $this->selectedKey, $this->days)
            : null;
    }

    /** @return array<string, array<string, string>> */
    #[Computed]
    public function pickable(): array
    {
        $team = $this->team();

        return $team ? app(MetricReader::class)->pickable($team) : [];
    }

    public function select(string $key): void
    {
        if ($this->isSelectable($key)) {
            $this->selectedKey = $key;
        }
        unset($this->series);
    }

    /** The picker is a bound <select>; a hand-edited value must not stick. */
    public function updatedSelectedKey(string $value): void
    {
        if (! $this->isSelectable($value)) {
            $this->selectedKey = '';
        }
        unset($this->series);
    }

    public function setDays(int $days): void
    {
        if (in_array($days, self::RANGES, true)) {
            $this->days = $days;
        }
        unset($this->series);
    }

    public function render(): View
    {
        return view('livewire.analytics.metrics-panel');
    }

    private function team(): ?Team
    {
        return Auth::user()?->currentTeam;
    }

    private function isSelectable(string $key): bool
    {
        foreach ($this->pickable as $metrics) {
            if (array_key_exists($key, $metrics)) {
                return true;
            }
        }

        return false;
    }

    /** Used by the view: the contract example needs the catalogue's platform keys. @return list<string> */
    public function platformKeys(): array
    {
        return (new MetricCatalogue)->platformKeys();
    }
}
