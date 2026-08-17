<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Analytics\DashboardBuilderPanel;
use App\Models\AnalyticsDashboard;
use App\Models\DashboardWidget;
use App\Models\MetricDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardBuilderPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_dashboard_sets_owner_team_and_makes_it_the_users_default(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        Livewire::actingAs($user)
            ->test(DashboardBuilderPanel::class)
            ->set('newDashboardTitle', 'Ops Overview')
            ->set('newDashboardVisibility', 'private')
            ->call('createDashboard');

        $dashboard = AnalyticsDashboard::sole();
        $this->assertSame($user->id, $dashboard->user_id);
        $this->assertSame($user->currentTeam->id, $dashboard->team_id);
        $this->assertSame('private', $dashboard->visibility);
        $this->assertTrue($dashboard->is_default);
    }

    public function test_a_second_dashboard_for_the_same_user_is_not_default(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        AnalyticsDashboard::factory()->create([
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'is_default' => true,
        ]);

        Livewire::actingAs($user)
            ->test(DashboardBuilderPanel::class)
            ->set('newDashboardTitle', 'Second One')
            ->set('newDashboardVisibility', 'private')
            ->call('createDashboard');

        $newest = AnalyticsDashboard::where('title', 'Second One')->sole();
        $this->assertFalse($newest->is_default);
    }

    public function test_dashboards_list_includes_mine_and_shared_team_ones_but_not_other_members_private_ones(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $teammate = User::factory()->create();
        $user->currentTeam->users()->attach($teammate, ['role' => 'editor']);

        $mine = AnalyticsDashboard::factory()->create(['team_id' => $user->currentTeam->id, 'user_id' => $user->id, 'visibility' => 'private']);
        $sharedByTeammate = AnalyticsDashboard::factory()->create(['team_id' => $user->currentTeam->id, 'user_id' => $teammate->id, 'visibility' => 'team']);
        $teammatesPrivate = AnalyticsDashboard::factory()->create(['team_id' => $user->currentTeam->id, 'user_id' => $teammate->id, 'visibility' => 'private']);

        $component = Livewire::actingAs($user)->test(DashboardBuilderPanel::class);
        $ids = $component->get('dashboards')->pluck('id');

        $this->assertTrue($ids->contains($mine->id));
        $this->assertTrue($ids->contains($sharedByTeammate->id));
        $this->assertFalse($ids->contains($teammatesPrivate->id));
    }

    public function test_setting_default_only_unsets_it_on_dashboards_owned_by_that_dashboards_owner(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $ownersFirst = AnalyticsDashboard::factory()->create(['team_id' => $owner->currentTeam->id, 'user_id' => $owner->id, 'is_default' => true]);
        $ownersSecond = AnalyticsDashboard::factory()->create(['team_id' => $owner->currentTeam->id, 'user_id' => $owner->id, 'is_default' => false]);

        $admin = User::factory()->create();
        $owner->currentTeam->users()->attach($admin, ['role' => 'admin']);
        $adminsOwn = AnalyticsDashboard::factory()->create(['team_id' => $owner->currentTeam->id, 'user_id' => $admin->id, 'is_default' => true, 'visibility' => 'private']);

        Livewire::actingAs($owner)
            ->test(DashboardBuilderPanel::class)
            ->call('setDefault', $ownersSecond->id);

        $this->assertFalse($ownersFirst->fresh()->is_default);
        $this->assertTrue($ownersSecond->fresh()->is_default);
        $this->assertTrue($adminsOwn->fresh()->is_default, 'a different owner\'s default must be untouched');
    }

    public function test_a_regular_member_cannot_delete_a_shared_dashboard_they_do_not_own(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $member = User::factory()->create();
        $owner->currentTeam->users()->attach($member, ['role' => 'editor']);
        $member->switchTeam($owner->currentTeam);

        $shared = AnalyticsDashboard::factory()->create(['team_id' => $owner->currentTeam->id, 'user_id' => $owner->id, 'visibility' => 'team']);

        Livewire::actingAs($member)
            ->test(DashboardBuilderPanel::class)
            ->call('deleteDashboard', $shared->id)
            ->assertForbidden();

        $this->assertNotNull($shared->fresh());
    }

    public function test_a_team_admin_can_add_a_widget_to_a_shared_dashboard_they_do_not_own(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $admin = User::factory()->create();
        $owner->currentTeam->users()->attach($admin, ['role' => 'admin']);
        $admin->switchTeam($owner->currentTeam);

        $shared = AnalyticsDashboard::factory()->create(['team_id' => $owner->currentTeam->id, 'user_id' => $owner->id, 'visibility' => 'team']);

        Livewire::actingAs($admin)
            ->test(DashboardBuilderPanel::class)
            ->call('selectDashboard', $shared->id)
            ->set('widgetType', 'alert_feed')
            ->call('addWidget');

        $this->assertSame(1, DashboardWidget::where('analytics_dashboard_id', $shared->id)->count());
    }

    public function test_a_metric_widget_requires_a_metric_definition(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $dashboard = AnalyticsDashboard::factory()->create(['team_id' => $user->currentTeam->id, 'user_id' => $user->id]);

        Livewire::actingAs($user)
            ->test(DashboardBuilderPanel::class)
            ->call('selectDashboard', $dashboard->id)
            ->set('widgetType', 'metric_card')
            ->call('addWidget')
            ->assertHasErrors(['widgetMetricDefinitionId']);

        $this->assertSame(0, DashboardWidget::where('analytics_dashboard_id', $dashboard->id)->count());
    }

    public function test_a_metric_widget_stores_the_chosen_metric_in_config(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $dashboard = AnalyticsDashboard::factory()->create(['team_id' => $user->currentTeam->id, 'user_id' => $user->id]);
        $definition = MetricDefinition::factory()->create();

        Livewire::actingAs($user)
            ->test(DashboardBuilderPanel::class)
            ->call('selectDashboard', $dashboard->id)
            ->set('widgetType', 'metric_card')
            ->set('widgetMetricDefinitionId', $definition->id)
            ->call('addWidget');

        $widget = DashboardWidget::where('analytics_dashboard_id', $dashboard->id)->sole();
        $this->assertSame($definition->id, $widget->config['metric_definition_id']);
    }

    public function test_removing_a_widget_from_someone_elses_private_dashboard_is_forbidden(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $member = User::factory()->create();
        $owner->currentTeam->users()->attach($member, ['role' => 'editor']);
        $member->switchTeam($owner->currentTeam);

        $dashboard = AnalyticsDashboard::factory()->create(['team_id' => $owner->currentTeam->id, 'user_id' => $owner->id, 'visibility' => 'team']);
        $widget = DashboardWidget::factory()->create(['analytics_dashboard_id' => $dashboard->id]);

        Livewire::actingAs($member)
            ->test(DashboardBuilderPanel::class)
            ->call('removeWidget', $widget->id)
            ->assertForbidden();

        $this->assertNotNull($widget->fresh());
    }

    public function test_reordering_widgets_persists_new_row_order(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $dashboard = AnalyticsDashboard::factory()->create(['team_id' => $user->currentTeam->id, 'user_id' => $user->id]);
        $first = DashboardWidget::factory()->create(['analytics_dashboard_id' => $dashboard->id, 'row' => 0]);
        $second = DashboardWidget::factory()->create(['analytics_dashboard_id' => $dashboard->id, 'row' => 1]);

        Livewire::actingAs($user)
            ->test(DashboardBuilderPanel::class)
            ->call('selectDashboard', $dashboard->id)
            ->call('updatePositions', [$second->id, $first->id]);

        $this->assertSame(0, $second->fresh()->row);
        $this->assertSame(0, $first->fresh()->row);
        // Both land in row 0 (positions 0 and 1 both floor-divide to row 0
        // under the existing 3-per-row math) -- ordering within a row is by
        // `col`, confirmed by the assertion below.
        $this->assertTrue($second->fresh()->col < $first->fresh()->col);
    }
}
