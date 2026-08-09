<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Analytics\RecommendationsPanel;
use App\Models\Recommendation;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RecommendationsPanelAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_owner_can_action_a_pending_recommendation(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $recommendation = Recommendation::factory()->create([
            'team_id' => $owner->currentTeam->id,
            'status' => 'pending',
        ]);

        Livewire::actingAs($owner)
            ->test(RecommendationsPanel::class)
            ->call('action', $recommendation->id);

        $this->assertSame('actioned', $recommendation->fresh()->status);
    }

    public function test_team_admin_can_dismiss_a_pending_recommendation(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $admin = User::factory()->create();
        $owner->currentTeam->users()->attach($admin, ['role' => 'admin']);
        $admin->switchTeam($owner->currentTeam);

        $recommendation = Recommendation::factory()->create([
            'team_id' => $owner->currentTeam->id,
            'status' => 'pending',
        ]);

        Livewire::actingAs($admin)
            ->test(RecommendationsPanel::class)
            ->call('dismiss', $recommendation->id);

        $this->assertSame('dismissed', $recommendation->fresh()->status);
    }

    public function test_non_admin_team_member_is_forbidden(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $member = User::factory()->create();
        $owner->currentTeam->users()->attach($member, ['role' => 'editor']);
        $member->switchTeam($owner->currentTeam);

        $recommendation = Recommendation::factory()->create([
            'team_id' => $owner->currentTeam->id,
            'status' => 'pending',
        ]);

        Livewire::actingAs($member)
            ->test(RecommendationsPanel::class)
            ->call('action', $recommendation->id)
            ->assertForbidden();

        $this->assertSame('pending', $recommendation->fresh()->status);
    }

    public function test_a_different_teams_owner_cannot_action_this_teams_recommendation(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $otherOwner = User::factory()->withPersonalTeam()->create();

        $recommendation = Recommendation::factory()->create([
            'team_id' => $owner->currentTeam->id,
            'status' => 'pending',
        ]);

        // Recommendation::HasTeamScope already applies a global scope keyed
        // on Auth::user()->currentTeam->id (see app/Models/Concerns/HasTeamScope.php),
        // so this lookup finds 0 rows for otherOwner's team and Eloquent
        // throws directly -- Livewire's test call() does not convert this
        // into an assertable HTTP response the way it does for
        // AuthorizationException, so the exception is asserted directly.
        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($otherOwner)
            ->test(RecommendationsPanel::class)
            ->call('action', $recommendation->id);

        // Unreachable once the exception above is thrown -- kept out of this
        // test body deliberately; the status-unchanged assertion belongs to
        // a separate test since PHPUnit halts here on the expected exception.
    }

    public function test_a_different_teams_owner_leaves_this_teams_recommendation_untouched(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $otherOwner = User::factory()->withPersonalTeam()->create();

        $recommendation = Recommendation::factory()->create([
            'team_id' => $owner->currentTeam->id,
            'status' => 'pending',
        ]);

        try {
            Livewire::actingAs($otherOwner)
                ->test(RecommendationsPanel::class)
                ->call('action', $recommendation->id);
        } catch (ModelNotFoundException) {
            // Expected -- see test_a_different_teams_owner_cannot_action_this_teams_recommendation.
        }

        $this->assertSame('pending', $recommendation->fresh()->status);
    }
}
