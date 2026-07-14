<?php

namespace Tests\Feature\Api;

use App\Models\CrossPlatformInsight;
use App\Models\DataSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntelligenceApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): array
    {
        $user  = User::factory()->withPersonalTeam()->create();
        $token = $user->createToken('test')->plainTextToken;
        return [$user, $token];
    }

    public function test_engines_returns_engine_registry(): void
    {
        [$user, $token] = $this->actingAsUser();

        $response = $this->withToken($token)->getJson('/api/v1/intelligence/engines');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => ['engines', 'active', 'active_count', 'connected_platforms'],
            ]);

        $this->assertGreaterThanOrEqual(17, count($response->json('data.engines')));
    }

    public function test_run_dispatches_engines_and_returns_count(): void
    {
        [$user, $token] = $this->actingAsUser();
        $team           = $user->currentTeam;

        DataSource::factory()->create([
            'team_id'  => $team->id,
            'platform' => 'dot.fleet',
            'status'   => 'connected',
        ]);

        $response = $this->withToken($token)->postJson('/api/v1/intelligence/run');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['engines_dispatched']]);

        $this->assertGreaterThan(0, $response->json('data.engines_dispatched'));
    }

    public function test_run_with_specific_engine_only_dispatches_that_engine(): void
    {
        [$user, $token] = $this->actingAsUser();
        $team           = $user->currentTeam;

        DataSource::factory()->create([
            'team_id'  => $team->id,
            'platform' => 'dot.hear',
            'status'   => 'connected',
        ]);

        $response = $this->withToken($token)->postJson('/api/v1/intelligence/run', [
            'engines' => ['community'],
        ]);

        $response->assertOk();
        $this->assertEquals(1, $response->json('data.engines_dispatched'));
    }

    public function test_insights_returns_paginated_list(): void
    {
        [$user, $token] = $this->actingAsUser();
        $team           = $user->currentTeam;

        CrossPlatformInsight::factory()->count(5)->create(['team_id' => $team->id]);

        $response = $this->withToken($token)->getJson('/api/v1/intelligence/insights');

        $response->assertOk()
            ->assertJsonStructure(['success', 'data', 'meta' => ['current_page', 'total']]);

        $this->assertGreaterThanOrEqual(5, $response->json('meta.total'));
    }

    public function test_insights_filters_by_severity(): void
    {
        [$user, $token] = $this->actingAsUser();
        $team           = $user->currentTeam;

        CrossPlatformInsight::factory()->create(['team_id' => $team->id, 'severity' => 'critical']);
        CrossPlatformInsight::factory()->create(['team_id' => $team->id, 'severity' => 'info']);

        $response = $this->withToken($token)->getJson('/api/v1/intelligence/insights?severity=critical');

        $response->assertOk();
        foreach ($response->json('data') as $insight) {
            $this->assertEquals('critical', $insight['severity']);
        }
    }

    public function test_graph_returns_statistics(): void
    {
        [$user, $token] = $this->actingAsUser();

        $response = $this->withToken($token)->getJson('/api/v1/intelligence/graph');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['node_count', 'edge_count', 'entity_types', 'density'],
            ]);
    }

    public function test_traverse_validates_required_fields(): void
    {
        [$user, $token] = $this->actingAsUser();

        $response = $this->withToken($token)->postJson('/api/v1/intelligence/graph/traverse', []);

        $response->assertUnprocessable();
    }

    public function test_traverse_returns_reachable_nodes(): void
    {
        [$user, $token] = $this->actingAsUser();

        $response = $this->withToken($token)->postJson('/api/v1/intelligence/graph/traverse', [
            'entity_type' => 'customer',
            'entity_id'   => 'C-NONEXISTENT',
            'max_depth'   => 2,
        ]);

        $response->assertOk()
            ->assertJsonStructure(['data' => ['nodes', 'node_count']]);
    }

    public function test_intelligence_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/intelligence/engines')->assertUnauthorized();
        $this->getJson('/api/v1/intelligence/insights')->assertUnauthorized();
        $this->postJson('/api/v1/intelligence/run')->assertUnauthorized();
    }
}
