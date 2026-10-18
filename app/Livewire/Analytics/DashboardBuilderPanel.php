<?php

namespace App\Livewire\Analytics;

use App\Models\AnalyticsDashboard;
use App\Models\DashboardWidget;
use App\Services\Metrics\MetricReader;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Dashboard Builder Panel
 *
 * Saved dashboards made of real metric widgets, and nothing else:
 *
 *   kpi    one metric as a tile: value, change since the previous report, sparkline
 *   trend  one metric's stored daily points as a chart
 *
 * Both read computed_metrics through MetricReader, so a widget can only ever show
 * what a connected platform reported ("no data yet" otherwise). The earlier
 * placeholder types (metric_card, chart, alert_feed ... ) rendered only their own
 * name; they cannot be added any more and any that remain say they were retired.
 *
 * Everyone on the team reads; arranging needs the edit-dashboards gate (owner,
 * admin, editor; not the read-only viewer).
 */
class DashboardBuilderPanel extends Component
{
    public ?int $activeDashboardId = null;

    public string $newDashboardTitle = '';

    public bool $showNewDashboard = false;

    // Widget form state
    public string $widgetType = 'kpi';

    public string $widgetMetric = '';

    public string $widgetTitle = '';

    public bool $showAddWidget = false;

    public const WIDGET_TYPES = [
        'kpi' => 'KPI tile',
        'trend' => 'Trend chart',
    ];

    protected array $rules = [
        'newDashboardTitle' => 'required|string|max:120',
        'widgetTitle' => 'nullable|string|max:120',
    ];

    #[Computed]
    public function dashboards(): Collection
    {
        return AnalyticsDashboard::orderBy('is_default', 'desc')
            ->orderBy('title')
            ->get();
    }

    #[Computed]
    public function activeDashboard(): ?AnalyticsDashboard
    {
        if (! $this->activeDashboardId) {
            return $this->dashboards->first();
        }

        return AnalyticsDashboard::find($this->activeDashboardId);
    }

    #[Computed]
    public function widgets(): Collection
    {
        return $this->activeDashboard
            ? DashboardWidget::where('analytics_dashboard_id', $this->activeDashboard->id)
                ->orderBy('row')->orderBy('col')->orderBy('id')
                ->get()
            : collect();
    }

    /**
     * What each widget currently shows, keyed by widget id. A widget whose
     * metric is gone (platform disconnected) gets 'unavailable'; an old
     * placeholder type gets 'retired'. Nothing is ever filled in.
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function rendered(): array
    {
        $team = Auth::user()?->currentTeam;
        $reader = app(MetricReader::class);
        $out = [];

        foreach ($this->widgets as $widget) {
            if (! array_key_exists($widget->widget_type, self::WIDGET_TYPES)) {
                $out[$widget->id] = ['kind' => 'retired'];

                continue;
            }

            $key = (string) ($widget->config['metric_key'] ?? '');
            $tile = $team ? $reader->tile($team, $key) : null;

            if ($tile === null) {
                $out[$widget->id] = ['kind' => 'unavailable', 'metric_key' => $key];

                continue;
            }

            $out[$widget->id] = $widget->widget_type === 'trend'
                ? ['kind' => 'trend', 'tile' => $tile, 'series' => $reader->series($team, $key, (int) ($widget->config['days'] ?? 30))]
                : ['kind' => 'kpi', 'tile' => $tile];
        }

        return $out;
    }

    /** @return array<string, array<string, string>> platform label => [metric key => label] */
    #[Computed]
    public function pickable(): array
    {
        $team = Auth::user()?->currentTeam;

        return $team ? app(MetricReader::class)->pickable($team) : [];
    }

    public function createDashboard(): void
    {
        Gate::authorize('edit-dashboards');

        $this->validateOnly('newDashboardTitle');

        $team = Auth::user()->currentTeam;
        $isFirst = ! AnalyticsDashboard::exists();

        $dashboard = AnalyticsDashboard::create([
            'team_id' => $team->id,
            'user_id' => Auth::id(),
            'title' => $this->newDashboardTitle,
            'is_default' => $isFirst,
        ]);

        $this->activeDashboardId = $dashboard->id;
        $this->reset(['newDashboardTitle', 'showNewDashboard']);
        unset($this->dashboards, $this->activeDashboard);
    }

    public function selectDashboard(int $id): void
    {
        $this->activeDashboardId = $id;
        unset($this->activeDashboard, $this->widgets, $this->rendered);
    }

    public function setDefault(int $id): void
    {
        Gate::authorize('edit-dashboards');

        AnalyticsDashboard::query()->update(['is_default' => false]);
        AnalyticsDashboard::where('id', $id)->update(['is_default' => true]);
        unset($this->dashboards);
    }

    public function deleteDashboard(int $id): void
    {
        Gate::authorize('edit-dashboards');

        AnalyticsDashboard::findOrFail($id)->delete();

        if ($this->activeDashboardId === $id) {
            $this->activeDashboardId = null;
        }

        unset($this->dashboards, $this->activeDashboard, $this->widgets, $this->rendered);
    }

    public function addWidget(): void
    {
        Gate::authorize('edit-dashboards');

        $this->validate([
            'widgetType' => 'required|in:'.implode(',', array_keys(self::WIDGET_TYPES)),
            'widgetMetric' => 'required|string',
            'widgetTitle' => 'nullable|string|max:120',
        ], [
            'widgetType.in' => 'Choose KPI tile or Trend chart.',
            'widgetMetric.required' => 'Choose a metric.',
        ]);

        $label = $this->labelFor($this->widgetMetric);
        if ($label === null) {
            $this->addError('widgetMetric', 'That metric is not available: only metrics from connected platforms can be shown.');

            return;
        }

        $dashboard = $this->activeDashboard;
        if (! $dashboard) {
            return;
        }

        // Place the widget in the next free slot of a three-wide grid
        $position = DashboardWidget::where('analytics_dashboard_id', $dashboard->id)->count();

        DashboardWidget::create([
            'analytics_dashboard_id' => $dashboard->id,
            'widget_type' => $this->widgetType,
            'title' => $this->widgetTitle ?: $label,
            'col' => ($position % 3) * 4,
            'row' => intdiv($position, 3),
            'width' => $this->widgetType === 'trend' ? 8 : 4,
            'height' => 2,
            'config' => ['metric_key' => $this->widgetMetric],
        ]);

        $this->reset(['widgetType', 'widgetMetric', 'widgetTitle', 'showAddWidget']);
        unset($this->widgets, $this->rendered);
    }

    public function removeWidget(int $id): void
    {
        Gate::authorize('edit-dashboards');

        DashboardWidget::whereHas('dashboard')
            ->findOrFail($id)
            ->delete();

        unset($this->widgets, $this->rendered);
    }

    /**
     * Move a widget one place earlier or later. Positions are renumbered into a
     * three-wide grid so the order is always unambiguous.
     */
    public function moveWidget(int $id, string $direction): void
    {
        Gate::authorize('edit-dashboards');

        $widget = DashboardWidget::whereHas('dashboard')->findOrFail($id);

        $ordered = DashboardWidget::where('analytics_dashboard_id', $widget->analytics_dashboard_id)
            ->orderBy('row')->orderBy('col')->orderBy('id')
            ->pluck('id')->all();

        $index = array_search($widget->id, $ordered, true);
        $target = $direction === 'earlier' ? $index - 1 : ($direction === 'later' ? $index + 1 : $index);

        if ($index === false || $target === $index || $target < 0 || $target >= count($ordered)) {
            return;
        }

        [$ordered[$index], $ordered[$target]] = [$ordered[$target], $ordered[$index]];

        foreach ($ordered as $position => $widgetId) {
            DashboardWidget::where('id', $widgetId)->update([
                'row' => intdiv($position, 3),
                'col' => ($position % 3) * 4,
            ]);
        }

        unset($this->widgets, $this->rendered);
    }

    public function render(): View
    {
        return view('livewire.analytics.dashboard-builder-panel');
    }

    private function labelFor(string $key): ?string
    {
        foreach ($this->pickable as $metrics) {
            if (array_key_exists($key, $metrics)) {
                return $metrics[$key];
            }
        }

        return null;
    }
}
