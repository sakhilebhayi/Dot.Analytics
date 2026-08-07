<?php

namespace Tests\Feature\Api;

use App\Models\DataSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformApiTest extends TestCase
{
    use RefreshDatabase;

    private function authenticatedUser(): array
    {
        $user = User::factory()->withPersonalTeam()->create();
        $token = $user->createToken('test')->plainTextToken;

        return [$user, $token];
    }

    public function test_catalog_returns_all_platforms(): void
    {
        [$user, $token] = $this->authenticatedUser();

        $response = $this->withToken($token)->getJson('/api/v1/platforms');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'data']);

        $this->assertCount(15, $response->json('data'));
    }

    public function test_connected_returns_only_connected_platforms(): void
    {
        [$user, $token] = $this->authenticatedUser();
        $team = $user->currentTeam;

        DataSource::factory()->create([
            'team_id' => $team->id,
            'platform' => 'dot.fleet',
            'status' => 'connected',
        ]);

        DataSource::factory()->create([
            'team_id' => $team->id,
            'platform' => 'dot.crm',
            'status' => 'pending',
        ]);

        $response = $this->withToken($token)->getJson('/api/v1/platforms/connected');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_connect_creates_data_source_for_known_platform(): void
    {
        [$user, $token] = $this->authenticatedUser();

        $response = $this->withToken($token)->postJson('/api/v1/platforms/dot.fleet/connect', [
            'base_url' => 'https://fleet.infodot.app',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('data_sources', [
            'team_id' => $user->currentTeam->id,
            'platform' => 'dot.fleet',
            'status' => 'connected',
        ]);
    }

    public function test_connect_returns_422_for_unknown_platform(): void
    {
        [$user, $token] = $this->authenticatedUser();

        $response = $this->withToken($token)->postJson('/api/v1/platforms/dot.unknown/connect');

        $response->assertUnprocessable();
    }

    public function test_disconnect_removes_platform(): void
    {
        [$user, $token] = $this->authenticatedUser();
        $team = $user->currentTeam;

        DataSource::factory()->create([
            'team_id' => $team->id,
            'platform' => 'dot.fleet',
            'status' => 'connected',
        ]);

        $response = $this->withToken($token)->deleteJson('/api/v1/platforms/dot.fleet');

        $response->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseMissing('data_sources', [
            'team_id' => $team->id,
            'platform' => 'dot.fleet',
        ]);
    }

    public function test_disconnect_returns_404_for_unconnected_platform(): void
    {
        [$user, $token] = $this->authenticatedUser();

        $response = $this->withToken($token)->deleteJson('/api/v1/platforms/dot.fleet');

        $response->assertNotFound();
    }

    public function test_show_returns_platform_details(): void
    {
        [$user, $token] = $this->authenticatedUser();
        $team = $user->currentTeam;

        DataSource::factory()->create([
            'team_id' => $team->id,
            'platform' => 'dot.crm',
            'status' => 'connected',
        ]);

        $response = $this->withToken($token)->getJson('/api/v1/platforms/dot.crm');

        $response->assertOk()
            ->assertJsonStructure(['success', 'data' => ['source', 'definition']]);
    }

    public function test_show_returns_404_for_non_connected_platform(): void
    {
        [$user, $token] = $this->authenticatedUser();

        $response = $this->withToken($token)->getJson('/api/v1/platforms/dot.fleet');

        $response->assertNotFound();
    }

    public function test_api_requires_authentication(): void
    {
        $this->getJson('/api/v1/platforms')->assertUnauthorized();
    }

    public function test_cannot_access_another_teams_platforms(): void
    {
        [$userA, $tokenA] = $this->authenticatedUser();
        [$userB, $tokenB] = $this->authenticatedUser();

        DataSource::factory()->create([
            'team_id' => $userB->currentTeam->id,
            'platform' => 'dot.fleet',
            'status' => 'connected',
        ]);

        // User A should see no connected platforms
        $response = $this->withToken($tokenA)->getJson('/api/v1/platforms/connected');

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }
}
