<?php

namespace Tests\Feature\Policies;

use App\Models\AnalyticsDashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsDashboardPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_update_and_delete_their_own_private_dashboard(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $dashboard = AnalyticsDashboard::factory()->create([
            'team_id' => $owner->currentTeam->id,
            'user_id' => $owner->id,
            'visibility' => 'private',
        ]);

        $this->assertTrue($owner->can('view', $dashboard));
        $this->assertTrue($owner->can('update', $dashboard));
        $this->assertTrue($owner->can('delete', $dashboard));
    }

    public function test_other_team_member_cannot_view_a_private_dashboard_they_do_not_own(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $member = User::factory()->create();
        $owner->currentTeam->users()->attach($member, ['role' => 'editor']);
        $member->switchTeam($owner->currentTeam);

        $dashboard = AnalyticsDashboard::factory()->create([
            'team_id' => $owner->currentTeam->id,
            'user_id' => $owner->id,
            'visibility' => 'private',
        ]);

        $this->assertFalse($member->can('view', $dashboard));
    }

    public function test_any_team_member_can_view_a_team_visibility_dashboard(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $member = User::factory()->create();
        $owner->currentTeam->users()->attach($member, ['role' => 'editor']);
        $member->switchTeam($owner->currentTeam);

        $dashboard = AnalyticsDashboard::factory()->create([
            'team_id' => $owner->currentTeam->id,
            'user_id' => $owner->id,
            'visibility' => 'team',
        ]);

        $this->assertTrue($member->can('view', $dashboard));
    }

    public function test_team_admin_can_update_and_delete_a_team_visibility_dashboard_they_do_not_own(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $admin = User::factory()->create();
        $owner->currentTeam->users()->attach($admin, ['role' => 'admin']);
        $admin->switchTeam($owner->currentTeam);

        $dashboard = AnalyticsDashboard::factory()->create([
            'team_id' => $owner->currentTeam->id,
            'user_id' => $owner->id,
            'visibility' => 'team',
        ]);

        $this->assertTrue($admin->can('update', $dashboard));
        $this->assertTrue($admin->can('delete', $dashboard));
    }

    public function test_regular_member_cannot_update_a_team_visibility_dashboard_they_do_not_own(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $member = User::factory()->create();
        $owner->currentTeam->users()->attach($member, ['role' => 'editor']);
        $member->switchTeam($owner->currentTeam);

        $dashboard = AnalyticsDashboard::factory()->create([
            'team_id' => $owner->currentTeam->id,
            'user_id' => $owner->id,
            'visibility' => 'team',
        ]);

        $this->assertFalse($member->can('update', $dashboard));
        $this->assertFalse($member->can('delete', $dashboard));
    }

    public function test_admin_still_cannot_update_a_private_dashboard_they_do_not_own(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $admin = User::factory()->create();
        $owner->currentTeam->users()->attach($admin, ['role' => 'admin']);
        $admin->switchTeam($owner->currentTeam);

        $dashboard = AnalyticsDashboard::factory()->create([
            'team_id' => $owner->currentTeam->id,
            'user_id' => $owner->id,
            'visibility' => 'private',
        ]);

        $this->assertFalse($admin->can('update', $dashboard));
        $this->assertFalse($admin->can('delete', $dashboard));
    }

    public function test_a_different_teams_user_cannot_view_a_team_visibility_dashboard(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $outsider = User::factory()->withPersonalTeam()->create();

        $dashboard = AnalyticsDashboard::factory()->create([
            'team_id' => $owner->currentTeam->id,
            'user_id' => $owner->id,
            'visibility' => 'team',
        ]);

        $this->assertFalse($outsider->can('view', $dashboard));
    }

    public function test_owner_who_switched_to_a_different_current_team_cannot_update_their_own_old_dashboard(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $otherTeamOwner = User::factory()->withPersonalTeam()->create();
        $otherTeamOwner->currentTeam->users()->attach($owner, ['role' => 'editor']);

        $dashboard = AnalyticsDashboard::factory()->create([
            'team_id' => $owner->currentTeam->id,
            'user_id' => $owner->id,
            'visibility' => 'private',
        ]);

        // $owner switches active team away from the team the dashboard belongs to.
        $owner->switchTeam($otherTeamOwner->currentTeam);

        $this->assertFalse($owner->can('view', $dashboard));
        $this->assertFalse($owner->can('update', $dashboard));
    }
}
