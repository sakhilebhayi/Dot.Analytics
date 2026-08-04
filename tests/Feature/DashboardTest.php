<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EnsureTeamContext-equivalent coverage: no middleware in this platform
     * guarantees current_team_id is set (see routes/web.php). A user who
     * belongs to no team (e.g. removed from their last team, or freshly
     * registered without team creation) must be redirected to team
     * creation instead of crashing on a null currentTeam dereference.
     */
    public function test_authenticated_user_with_no_team_is_redirected_to_team_creation(): void
    {
        $user = User::factory()->create(['current_team_id' => null]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect(route('teams.create'));
    }

    public function test_authenticated_user_with_team_sees_dashboard(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
    }
}
