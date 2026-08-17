<?php

namespace Tests\Feature;

use App\Models\IntelligenceNode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_team_member_can_view_the_search_page(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user)
            ->get(route('search'))
            ->assertOk();
    }

    public function test_a_query_string_reaches_the_component(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        IntelligenceNode::factory()->create(['team_id' => $user->currentTeam->id, 'label' => 'Findable Corp']);

        $this->actingAs($user)
            ->get(route('search', ['q' => 'Findable']))
            ->assertOk()
            ->assertSee('Findable Corp');
    }

    public function test_a_user_with_no_current_team_is_redirected_to_team_creation(): void
    {
        $user = User::factory()->create(['current_team_id' => null]);

        $this->actingAs($user)
            ->get(route('search'))
            ->assertRedirect(route('teams.create'));
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get(route('search'))
            ->assertRedirect(route('login'));
    }
}
