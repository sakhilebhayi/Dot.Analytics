<?php

namespace Tests\Unit\Policies;

use App\Models\CrossPlatformInsight;
use App\Models\DataSource;
use App\Models\User;
use App\Policies\CrossPlatformInsightPolicy;
use App\Policies\DataSourcePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PoliciesTest extends TestCase
{
    use RefreshDatabase;

    // ─── DataSourcePolicy ─────────────────────────────────────────────────────

    public function test_data_source_view_any_requires_team(): void
    {
        $policy = new DataSourcePolicy;
        $user = User::factory()->withPersonalTeam()->create();

        $this->assertTrue($policy->viewAny($user));
    }

    public function test_data_source_view_allows_team_member(): void
    {
        $policy = new DataSourcePolicy;
        $user = User::factory()->withPersonalTeam()->create();
        $source = DataSource::factory()->create(['team_id' => $user->currentTeam->id]);

        $this->assertTrue($policy->view($user, $source));
    }

    public function test_data_source_view_denies_other_team(): void
    {
        $policy = new DataSourcePolicy;
        $userA = User::factory()->withPersonalTeam()->create();
        $userB = User::factory()->withPersonalTeam()->create();
        $source = DataSource::factory()->create(['team_id' => $userA->currentTeam->id]);

        $this->assertFalse($policy->view($userB, $source));
    }

    public function test_data_source_create_requires_team(): void
    {
        $policy = new DataSourcePolicy;
        $user = User::factory()->withPersonalTeam()->create();

        $this->assertTrue($policy->create($user));
    }

    public function test_data_source_update_allows_team_owner(): void
    {
        $policy = new DataSourcePolicy;
        $user = User::factory()->withPersonalTeam()->create();
        $source = DataSource::factory()->create(['team_id' => $user->currentTeam->id]);

        $this->assertTrue($policy->update($user, $source));
    }

    public function test_data_source_update_denies_other_team(): void
    {
        $policy = new DataSourcePolicy;
        $userA = User::factory()->withPersonalTeam()->create();
        $userB = User::factory()->withPersonalTeam()->create();
        $source = DataSource::factory()->create(['team_id' => $userA->currentTeam->id]);

        $this->assertFalse($policy->update($userB, $source));
    }

    public function test_data_source_delete_allows_owner(): void
    {
        $policy = new DataSourcePolicy;
        $user = User::factory()->withPersonalTeam()->create();
        $source = DataSource::factory()->create(['team_id' => $user->currentTeam->id]);

        $this->assertTrue($policy->delete($user, $source));
    }

    // ─── CrossPlatformInsightPolicy ───────────────────────────────────────────

    public function test_insight_view_any_requires_team(): void
    {
        $policy = new CrossPlatformInsightPolicy;
        $user = User::factory()->withPersonalTeam()->create();

        $this->assertTrue($policy->viewAny($user));
    }

    public function test_insight_view_allows_team_member(): void
    {
        $policy = new CrossPlatformInsightPolicy;
        $user = User::factory()->withPersonalTeam()->create();
        $insight = CrossPlatformInsight::factory()->create(['team_id' => $user->currentTeam->id]);

        $this->assertTrue($policy->view($user, $insight));
    }

    public function test_insight_view_denies_other_team(): void
    {
        $policy = new CrossPlatformInsightPolicy;
        $userA = User::factory()->withPersonalTeam()->create();
        $userB = User::factory()->withPersonalTeam()->create();
        $insight = CrossPlatformInsight::factory()->create(['team_id' => $userA->currentTeam->id]);

        $this->assertFalse($policy->view($userB, $insight));
    }

    public function test_insight_update_allows_team_member(): void
    {
        $policy = new CrossPlatformInsightPolicy;
        $user = User::factory()->withPersonalTeam()->create();
        $insight = CrossPlatformInsight::factory()->create(['team_id' => $user->currentTeam->id]);

        $this->assertTrue($policy->update($user, $insight));
    }

    public function test_insight_delete_allows_team_owner(): void
    {
        $policy = new CrossPlatformInsightPolicy;
        $user = User::factory()->withPersonalTeam()->create();
        $insight = CrossPlatformInsight::factory()->create(['team_id' => $user->currentTeam->id]);

        $this->assertTrue($policy->delete($user, $insight));
    }
}
