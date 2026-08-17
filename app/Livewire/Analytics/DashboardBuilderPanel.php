<?php

namespace App\Livewire\Analytics;

use App\Models\AnalyticsDashboard;
use App\Models\DashboardWidget;
use App\Models\MetricDefinition;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Dashboard Builder Panel
 *
 * Lets team members compose custom intelligence dashboards from widgets.
 * A dashboard is either private (visible only to its creator) or shared
 * with the team (visible to everyone, editable by its creator or a team
 * admin/owner) -- see AnalyticsDashboardPolicy for the exact rules.
 *
 * Five widget types embed this app's own alert/recommendation/insight/DNA/
 * briefing panels directly. The other two (metric_card, chart) read
 * MetricDefinition/ComputedMetric and are rendered by dedicated Blade
 * partials under resources/views/livewire/analytics/widgets/.
 *
 * Drag-and-drop reordering is handled client-side via Alpine.js + Sortable.js
 * and persists position via the `updatePositions` action.
 */
class DashboardBuilderPanel extends Component
{
    public ?int $activeDashboardId = null;

    public string $newDashboardTitle = '';

    public string $newDashboardVisibility = 'private';

    public bool $showNewDashboard = false;

    // Widget form state
    public string $widgetType = 'metric_card';

    public string $widgetTitle = '';

    public ?int $widgetMetricDefinitionId = null;

    public bool $showAddWidget = false;

    public const WIDGET_TYPES = [
        'metric_card' => 'Metric Card',
        'chart' => 'Chart',
        'alert_feed' => 'Alert Feed',
        'recommendation_feed' => 'Recommendation Feed',
        'insight_feed' => 'Cross-Platform Insights',
        'dna_snapshot' => 'Business DNA Snapshot',
        'briefing_summary' => 'Executive Briefing Summary',
    ];

    /** Widget types that require picking a MetricDefinition. */
    public const METRIC_WIDGET_TYPES = ['metric_card', 'chart'];

    protected function rules(): array
    {
        return [
            'newDashboardTitle' => 'required|string|max:120',
            'newDashboardVisibility' => 'required|in:private,team',
            'widgetType' => 'required|string|in:'.implode(',', array_keys(self::WIDGET_TYPES)),
            'widgetTitle' => 'nullable|string|max:120',
            'widgetMetricDefinitionId' => 'required_if:widgetType,'.implode(',', self::METRIC_WIDGET_TYPES).'|nullable|exists:metric_definitions,id',
        ];
    }

    #[Computed]
    public function dashboards(): Collection
    {
        return AnalyticsDashboard::where(function ($query) {
            $query->where('user_id', Auth::id())
                ->orWhere('visibility', 'team');
        })
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

        return $this->dashboards->firstWhere('id', $this->activeDashboardId);
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

    #[Computed]
    public function metricDefinitions(): Collection
    {
        return MetricDefinition::orderBy('label')->get();
    }

    public function createDashboard(): void
    {
        Gate::authorize('create', AnalyticsDashboard::class);
        $this->validateOnly('newDashboardTitle');
        $this->validateOnly('newDashboardVisibility');

        $team = Auth::user()->currentTeam;
        $isFirstOwnDashboard = ! AnalyticsDashboard::where('user_id', Auth::id())->exists();

        $dashboard = AnalyticsDashboard::create([
            'team_id' => $team->id,
            'user_id' => Auth::id(),
            'title' => $this->newDashboardTitle,
            'visibility' => $this->newDashboardVisibility,
            'is_default' => $isFirstOwnDashboard,
        ]);

        $this->activeDashboardId = $dashboard->id;
        $this->reset(['newDashboardTitle', 'newDashboardVisibility', 'showNewDashboard']);
        unset($this->dashboards, $this->activeDashboard);
    }

    public function selectDashboard(int $id): void
    {
        $dashboard = AnalyticsDashboard::findOrFail($id);
        Gate::authorize('view', $dashboard);

        $this->activeDashboardId = $id;
        unset($this->activeDashboard, $this->widgets);
    }

    public function setDefault(int $id): void
    {
        $dashboard = AnalyticsDashboard::findOrFail($id);
        Gate::authorize('update', $dashboard);

        AnalyticsDashboard::where('user_id', $dashboard->user_id)->update(['is_default' => false]);
        $dashboard->update(['is_default' => true]);

        unset($this->dashboards);
    }

    public function deleteDashboard(int $id): void
    {
        $dashboard = AnalyticsDashboard::findOrFail($id);
        Gate::authorize('delete', $dashboard);

        $dashboard->delete();

        if ($this->activeDashboardId === $id) {
            $this->activeDashboardId = null;
        }

        unset($this->dashboards, $this->activeDashboard, $this->widgets);
    }

    public function addWidget(): void
    {
        $dashboard = $this->activeDashboard;
        if (! $dashboard) {
            return;
        }
        Gate::authorize('update', $dashboard);

        $this->validateOnly('widgetType');
        $this->validateOnly('widgetMetricDefinitionId');

        // Place widget in next available grid position
        $existingCount = DashboardWidget::where('analytics_dashboard_id', $dashboard->id)->count();
        $col = ($existingCount * 4) % 12;
        $row = (int) floor(($existingCount * 4) / 12);

        $config = ['type' => $this->widgetType];
        if (in_array($this->widgetType, self::METRIC_WIDGET_TYPES, true)) {
            $config['metric_definition_id'] = $this->widgetMetricDefinitionId;
        }

        DashboardWidget::create([
            'analytics_dashboard_id' => $dashboard->id,
            'widget_type' => $this->widgetType,
            'title' => $this->widgetTitle ?: self::WIDGET_TYPES[$this->widgetType] ?? $this->widgetType,
            'col' => $col,
            'row' => $row,
            'width' => 4,
            'height' => 2,
            'config' => $config,
        ]);

        $this->reset(['widgetType', 'widgetTitle', 'widgetMetricDefinitionId', 'showAddWidget']);
        unset($this->widgets);
    }

    public function removeWidget(int $id): void
    {
        $widget = DashboardWidget::with('dashboard')->findOrFail($id);
        if (! $widget->dashboard) {
            // The parent dashboard is outside the acting user's team --
            // AnalyticsDashboard's HasTeamScope global scope hides it from
            // the belongsTo lookup, so this looks the same as "not found".
            abort(404);
        }
        Gate::authorize('update', $widget->dashboard);

        $widget->delete();

        unset($this->widgets);
    }

    /**
     * Persist widget positions after drag-and-drop reorder.
     * Called from Alpine.js with the new order as an array of IDs.
     *
     * @param  array<int, int>  $orderedIds
     */
    public function updatePositions(array $orderedIds): void
    {
        $dashboard = $this->activeDashboard;
        if (! $dashboard) {
            return;
        }
        Gate::authorize('update', $dashboard);

        foreach ($orderedIds as $position => $widgetId) {
            DashboardWidget::where('analytics_dashboard_id', $dashboard->id)
                ->where('id', $widgetId)
                ->update([
                    'row' => (int) floor($position / 3),
                    'col' => ($position % 3) * 4,
                ]);
        }

        unset($this->widgets);
    }

    public function render(): View
    {
        return view('livewire.analytics.dashboard-builder-panel');
    }
}
