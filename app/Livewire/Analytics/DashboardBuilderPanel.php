<?php

namespace App\Livewire\Analytics;

use App\Models\AnalyticsDashboard;
use App\Models\DashboardWidget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Dashboard Builder Panel
 *
 * Allows teams to compose custom intelligence dashboards by adding,
 * configuring, and removing widgets. Supports metric cards, chart
 * placeholders, alert feeds, and recommendation feeds.
 *
 * Drag-and-drop reordering is handled client-side via Alpine.js + Sortable.js
 * and persists position via the `updatePositions` action.
 */
class DashboardBuilderPanel extends Component
{
    public ?int    $activeDashboardId = null;
    public string  $newDashboardTitle = '';
    public bool    $showNewDashboard  = false;

    // Widget form state
    public string  $widgetType   = 'metric_card';
    public string  $widgetTitle  = '';
    public bool    $showAddWidget = false;

    public const WIDGET_TYPES = [
        'metric_card'        => 'Metric Card',
        'chart'              => 'Chart',
        'alert_feed'         => 'Alert Feed',
        'recommendation_feed' => 'Recommendation Feed',
        'insight_feed'       => 'Cross-Platform Insights',
        'dna_snapshot'       => 'Business DNA Snapshot',
        'briefing_summary'   => 'Executive Briefing Summary',
    ];

    protected array $rules = [
        'newDashboardTitle' => 'required|string|max:120',
        'widgetType'        => 'required|string',
        'widgetTitle'       => 'nullable|string|max:120',
    ];

    #[Computed]
    public function dashboards(): Collection
    {
        return AnalyticsDashboard::where('team_id', Auth::user()->currentTeam->id)
            ->orderBy('is_default', 'desc')
            ->orderBy('title')
            ->get();
    }

    #[Computed]
    public function activeDashboard(): ?AnalyticsDashboard
    {
        if (! $this->activeDashboardId) {
            return $this->dashboards->first();
        }
        return AnalyticsDashboard::where('team_id', Auth::user()->currentTeam->id)
            ->find($this->activeDashboardId);
    }

    #[Computed]
    public function widgets(): Collection
    {
        return $this->activeDashboard
            ? DashboardWidget::where('analytics_dashboard_id', $this->activeDashboard->id)
                ->orderBy('row')->orderBy('col')
                ->get()
            : collect();
    }

    public function createDashboard(): void
    {
        $this->validateOnly('newDashboardTitle');

        $team      = Auth::user()->currentTeam;
        $isFirst   = ! AnalyticsDashboard::where('team_id', $team->id)->exists();

        $dashboard = AnalyticsDashboard::create([
            'team_id'    => $team->id,
            'user_id'    => Auth::id(),
            'title'      => $this->newDashboardTitle,
            'is_default' => $isFirst,
        ]);

        $this->activeDashboardId = $dashboard->id;
        $this->reset(['newDashboardTitle', 'showNewDashboard']);
        unset($this->dashboards, $this->activeDashboard);
    }

    public function selectDashboard(int $id): void
    {
        $this->activeDashboardId = $id;
        unset($this->activeDashboard, $this->widgets);
    }

    public function setDefault(int $id): void
    {
        $team = Auth::user()->currentTeam;
        AnalyticsDashboard::where('team_id', $team->id)->update(['is_default' => false]);
        AnalyticsDashboard::where('team_id', $team->id)->where('id', $id)->update(['is_default' => true]);
        unset($this->dashboards);
    }

    public function deleteDashboard(int $id): void
    {
        AnalyticsDashboard::where('team_id', Auth::user()->currentTeam->id)
            ->findOrFail($id)
            ->delete();

        if ($this->activeDashboardId === $id) {
            $this->activeDashboardId = null;
        }

        unset($this->dashboards, $this->activeDashboard, $this->widgets);
    }

    public function addWidget(): void
    {
        $this->validateOnly('widgetType');

        $dashboard = $this->activeDashboard;
        if (! $dashboard) {
            return;
        }

        // Place widget in next available grid position
        $existingCount = DashboardWidget::where('analytics_dashboard_id', $dashboard->id)->count();
        $col           = ($existingCount * 4) % 12;
        $row           = (int) floor(($existingCount * 4) / 12);

        DashboardWidget::create([
            'analytics_dashboard_id' => $dashboard->id,
            'widget_type'            => $this->widgetType,
            'title'                  => $this->widgetTitle ?: self::WIDGET_TYPES[$this->widgetType] ?? $this->widgetType,
            'col'                    => $col,
            'row'                    => $row,
            'width'                  => 4,
            'height'                 => 2,
            'config'                 => ['type' => $this->widgetType],
        ]);

        $this->reset(['widgetType', 'widgetTitle', 'showAddWidget']);
        unset($this->widgets);
    }

    public function removeWidget(int $id): void
    {
        DashboardWidget::whereHas('dashboard', fn ($q) => $q->where('team_id', Auth::user()->currentTeam->id))
            ->findOrFail($id)
            ->delete();

        unset($this->widgets);
    }

    /**
     * Persist widget positions after drag-and-drop reorder.
     * Called from Alpine.js with the new order as an array of IDs.
     *
     * @param array<int, int> $orderedIds
     */
    public function updatePositions(array $orderedIds): void
    {
        foreach ($orderedIds as $position => $widgetId) {
            DashboardWidget::whereHas('dashboard', fn ($q) =>
                $q->where('team_id', Auth::user()->currentTeam->id)
            )->where('id', $widgetId)->update([
                'row' => (int) floor($position / 3),
                'col' => ($position % 3) * 4,
            ]);
        }

        unset($this->widgets);
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.analytics.dashboard-builder-panel');
    }
}
