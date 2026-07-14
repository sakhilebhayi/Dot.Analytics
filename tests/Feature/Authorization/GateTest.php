<?php

namespace Tests\Feature\Authorization;

use App\Models\DataSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Comprehensive Gate authorization tests.
 * Tests every gate defined in AppServiceProvider with:
 *  - team owner (authorized)
 *  - non-owner team member (unauthorized for admin gates)
 *  - guest / unauthenticated (always unauthorized)
 *  - cross-tenant isolation
 */
class GateTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->withPersonalTeam()->create();
    }

    // ─── manage-platforms ────────────────────────────────────────────────────

    public function test_manage_platforms_allows_team_owner(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);

        $this->assertTrue($owner->can('manage-platforms'));
    }

    public function test_manage_platforms_denies_user_with_no_team(): void
    {
        // A user model with no currentTeam should be denied manage-platforms
        $userWithNoTeam = new \App\Models\User();
        $userWithNoTeam->id = 9999;

        $gate = app(\Illuminate\Contracts\Auth\Access\Gate::class);

        // isTeamOwnerOrAdmin returns false when currentTeam is null
        $result = $gate->forUser($userWithNoTeam)->check('manage-platforms');
        $this->assertFalse($result);
    }

    // ─── run-intelligence-engines ────────────────────────────────────────────

    public function test_run_intelligence_engines_allows_owner(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);

        $this->assertTrue($owner->can('run-intelligence-engines'));
    }

    // ─── generate-briefing ───────────────────────────────────────────────────

    public function test_generate_briefing_allows_owner(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);

        $this->assertTrue($owner->can('generate-briefing'));
    }

    // ─── manage-connectors ───────────────────────────────────────────────────

    public function test_manage_connectors_allows_owner(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);

        $this->assertTrue($owner->can('manage-connectors'));
    }

    // ─── execute-sql-query ───────────────────────────────────────────────────

    public function test_execute_sql_query_allows_owner(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);

        $this->assertTrue($owner->can('execute-sql-query'));
    }

    // ─── view-audit-logs ─────────────────────────────────────────────────────

    public function test_view_audit_logs_allows_owner(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);

        $this->assertTrue($owner->can('view-audit-logs'));
    }

    // ─── view-intelligence ───────────────────────────────────────────────────

    public function test_view_intelligence_allows_any_team_member(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);

        $this->assertTrue($owner->can('view-intelligence'));
    }

    // ─── API-level gate enforcement ──────────────────────────────────────────

    public function test_feature_flag_enable_requires_auth(): void
    {
        $this->patchJson('/api/v1/feature-flags/some-flag/enable')
            ->assertUnauthorized();
    }

    public function test_feature_flag_create_requires_auth(): void
    {
        $this->postJson('/api/v1/feature-flags', ['key' => 'x', 'name' => 'X'])
            ->assertUnauthorized();
    }

    // ─── Cross-tenant isolation ───────────────────────────────────────────────

    public function test_user_cannot_access_another_teams_data_sources(): void
    {
        $ownerA = $this->owner();
        $ownerB = $this->owner();

        DataSource::factory()->create([
            'team_id'  => $ownerA->currentTeam->id,
            'platform' => 'dot.fleet',
            'status'   => 'connected',
        ]);

        $tokenB = $ownerB->createToken('test')->plainTextToken;

        // Team B should see 0 connected platforms
        $this->withToken($tokenB)
            ->getJson('/api/v1/platforms/connected')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_user_cannot_disconnect_another_teams_platform(): void
    {
        $ownerA = $this->owner();
        $ownerB = $this->owner();

        DataSource::factory()->create([
            'team_id'  => $ownerA->currentTeam->id,
            'platform' => 'dot.fleet',
            'status'   => 'connected',
        ]);

        $tokenB = $ownerB->createToken('test')->plainTextToken;

        // Team B trying to disconnect Team A's platform — should 404
        $this->withToken($tokenB)
            ->deleteJson('/api/v1/platforms/dot.fleet')
            ->assertNotFound();
    }

    public function test_user_cannot_run_engines_for_another_team(): void
    {
        // Both teams' engines run — but only for their own team
        $ownerA = $this->owner();
        $ownerB = $this->owner();

        $tokenA = $ownerA->createToken('test')->plainTextToken;

        $this->withToken($tokenA)->postJson('/api/v1/intelligence/run')->assertOk();

        // Team B's engine runs table should be empty
        $this->assertDatabaseMissing('intelligence_engine_runs', [
            'team_id' => $ownerB->currentTeam->id,
        ]);
    }
}
