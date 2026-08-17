<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_team_member_can_view_the_dashboards_page(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user)
            ->get(route('dashboards.index'))
            ->assertOk()
            ->assertSee('My Dashboards');
    }

    public function test_a_user_with_no_current_team_is_redirected_to_team_creation(): void
    {
        $user = User::factory()->create(['current_team_id' => null]);

        $this->actingAs($user)
            ->get(route('dashboards.index'))
            ->assertRedirect(route('teams.create'));
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get(route('dashboards.index'))
            ->assertRedirect(route('login'));
    }
}
