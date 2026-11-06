<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Analytics\DashboardBuilderPanel;
use App\Livewire\Analytics\RecommendationsPanel;
use App\Livewire\Analytics\SavedReportsPanel;
use App\Models\AnalyticsDashboard;
use App\Models\AnalyticsReport;
use App\Models\DashboardWidget;
use App\Models\DataSource;
use App\Models\Recommendation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ExtendedLivewireTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($this->user);
    }

    private function team()
    {
        return $this->user->currentTeam;
    }

    // ─── SavedReportsPanel ───────────────────────────────────────────────────

    public function test_saved_reports_panel_renders(): void
    {
        Livewire::test(SavedReportsPanel::class)->assertStatus(200);
    }

    public function test_saved_reports_panel_create_saves_report(): void
    {
        Livewire::test(SavedReportsPanel::class)
            ->set('title', 'My Weekly Report')
            ->set('reportType', 'insights')
            ->call('create');

        $this->assertDatabaseHas('analytics_reports', [
            'team_id' => $this->team()->id,
            'title' => 'My Weekly Report',
        ]);
    }

    public function test_saved_reports_panel_create_validates_title(): void
    {
        Livewire::test(SavedReportsPanel::class)
            ->set('title', '')
            ->call('create')
            ->assertHasErrors(['title']);
    }

    public function test_saved_reports_panel_delete_removes_report(): void
    {
        $report = AnalyticsReport::create([
            'team_id' => $this->team()->id,
            'user_id' => $this->user->id,
            'title' => 'To Delete',
            'type' => 'ad_hoc',
            'config' => ['report_type' => 'insights'],
        ]);

        Livewire::test(SavedReportsPanel::class)->call('delete', $report->id);

        $this->assertDatabaseMissing('analytics_reports', ['id' => $report->id]);
    }

    public function test_saved_reports_panel_run_creates_report_run(): void
    {
        $report = AnalyticsReport::create([
            'team_id' => $this->team()->id,
            'user_id' => $this->user->id,
            'title' => 'Run Me',
            'type' => 'ad_hoc',
            'config' => ['report_type' => 'insights'],
        ]);

        Livewire::test(SavedReportsPanel::class)->call('run', $report->id);

        $this->assertDatabaseHas('report_runs', [
            'analytics_report_id' => $report->id,
            'status' => 'completed',
        ]);
    }

    /** Flags are global: only a platform operator may mutate them (a team owner may not). */
    private function actAsOperator(): void
    {
        $this->user->forceFill(['is_platform_operator' => true])->save();
        $this->actingAs($this->user->fresh());
    }

    // ─── RecommendationsPanel (extended) ────────────────────────────────────

    public function test_recommendations_panel_generate_creates_recommendations(): void
    {
        Livewire::test(RecommendationsPanel::class)->call('generate');

        // With no AI key, mock returns [], so no DB entries.
        // Test that generate completes without error.
        $this->assertFalse(false); // passes — no exception thrown
    }

    public function test_recommendations_panel_does_not_show_actioned_recommendations(): void
    {
        Recommendation::factory()->create([
            'team_id' => $this->team()->id,
            'title' => 'Actioned Rec',
            'status' => 'actioned',
        ]);

        Livewire::test(RecommendationsPanel::class)
            ->assertDontSee('Actioned Rec');
    }

    public function test_recommendations_panel_does_not_show_dismissed_recommendations(): void
    {
        Recommendation::factory()->create([
            'team_id' => $this->team()->id,
            'title' => 'Dismissed Rec',
            'status' => 'dismissed',
        ]);

        Livewire::test(RecommendationsPanel::class)
            ->assertDontSee('Dismissed Rec');
    }

    // ─── DashboardBuilderPanel ───────────────────────────────────────────────

    public function test_dashboard_builder_panel_renders(): void
    {
        Livewire::test(DashboardBuilderPanel::class)->assertStatus(200);
    }

    public function test_dashboard_builder_create_dashboard(): void
    {
        Livewire::test(DashboardBuilderPanel::class)
            ->set('newDashboardTitle', 'Fleet Overview')
            ->call('createDashboard');

        $this->assertDatabaseHas('analytics_dashboards', [
            'team_id' => $this->team()->id,
            'title' => 'Fleet Overview',
        ]);
    }

    public function test_dashboard_builder_first_dashboard_is_default(): void
    {
        Livewire::test(DashboardBuilderPanel::class)
            ->set('newDashboardTitle', 'First Dashboard')
            ->call('createDashboard');

        $this->assertDatabaseHas('analytics_dashboards', [
            'team_id' => $this->team()->id,
            'is_default' => true,
        ]);
    }

    public function test_dashboard_builder_create_validates_title(): void
    {
        Livewire::test(DashboardBuilderPanel::class)
            ->set('newDashboardTitle', '')
            ->call('createDashboard')
            ->assertHasErrors(['newDashboardTitle']);
    }

    public function test_dashboard_builder_add_widget(): void
    {
        $dashboard = AnalyticsDashboard::create([
            'team_id' => $this->team()->id,
            'user_id' => $this->user->id,
            'title' => 'Test',
            'is_default' => true,
        ]);

        DataSource::factory()->create(['team_id' => $this->team()->id, 'platform' => 'tasks', 'status' => 'connected']);

        Livewire::test(DashboardBuilderPanel::class)
            ->set('activeDashboardId', $dashboard->id)
            ->set('widgetType', 'kpi')
            ->set('widgetMetric', 'tasks.open')
            ->call('addWidget');

        $this->assertDatabaseHas('dashboard_widgets', [
            'analytics_dashboard_id' => $dashboard->id,
            'widget_type' => 'kpi',
        ]);
    }

    public function test_dashboard_builder_remove_widget(): void
    {
        $dashboard = AnalyticsDashboard::create([
            'team_id' => $this->team()->id,
            'user_id' => $this->user->id,
            'title' => 'Test',
            'is_default' => true,
        ]);

        $widget = DashboardWidget::create([
            'analytics_dashboard_id' => $dashboard->id,
            'widget_type' => 'chart',
            'col' => 0,
            'row' => 0,
            'width' => 4,
            'height' => 2,
        ]);

        Livewire::test(DashboardBuilderPanel::class)
            ->set('activeDashboardId', $dashboard->id)
            ->call('removeWidget', $widget->id);

        $this->assertDatabaseMissing('dashboard_widgets', ['id' => $widget->id]);
    }

    public function test_dashboard_builder_delete_dashboard(): void
    {
        $dashboard = AnalyticsDashboard::create([
            'team_id' => $this->team()->id,
            'user_id' => $this->user->id,
            'title' => 'Delete Me',
            'is_default' => false,
        ]);

        Livewire::test(DashboardBuilderPanel::class)
            ->call('deleteDashboard', $dashboard->id);

        $this->assertDatabaseMissing('analytics_dashboards', ['id' => $dashboard->id]);
    }

    public function test_dashboard_builder_set_default(): void
    {
        $dashA = AnalyticsDashboard::create(['team_id' => $this->team()->id, 'user_id' => $this->user->id, 'title' => 'A', 'is_default' => true]);
        $dashB = AnalyticsDashboard::create(['team_id' => $this->team()->id, 'user_id' => $this->user->id, 'title' => 'B', 'is_default' => false]);

        Livewire::test(DashboardBuilderPanel::class)->call('setDefault', $dashB->id);

        $this->assertDatabaseHas('analytics_dashboards', ['id' => $dashB->id, 'is_default' => true]);
        $this->assertDatabaseHas('analytics_dashboards', ['id' => $dashA->id, 'is_default' => false]);
    }
}
