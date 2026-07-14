<?php

namespace Tests\Feature\Api;

use App\Models\DataConnector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SqlApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): array
    {
        $user  = User::factory()->withPersonalTeam()->create();
        $token = $user->createToken('test')->plainTextToken;
        return [$user, $token];
    }

    public function test_generate_returns_sql_for_valid_question(): void
    {
        [$user, $token] = $this->actingAsUser();

        $connector = DataConnector::create([
            'team_id' => $user->currentTeam->id,
            'name'    => 'Test DB',
            'type'    => 'database',
            'driver'  => 'sqlite',
            'config'  => ['driver' => 'sqlite', 'database' => ':memory:'],
            'status'  => 'active',
        ]);

        $response = $this->withToken($token)->postJson('/api/v1/sql/generate', [
            'question'     => 'Show me all users ordered by name',
            'connector_id' => $connector->id,
            'schema'       => ['tables' => [['name' => 'users']]],
        ]);

        // With no AI key configured, the router returns a mock message.
        // We assert on the response structure, not the SQL content.
        $response->assertOk()
            ->assertJsonStructure(['data' => ['sql', 'question']]);
    }

    public function test_generate_validates_required_fields(): void
    {
        [$user, $token] = $this->actingAsUser();

        $this->withToken($token)->postJson('/api/v1/sql/generate', [])
            ->assertUnprocessable();
    }

    public function test_generate_validates_question_min_length(): void
    {
        [$user, $token] = $this->actingAsUser();

        $connector = DataConnector::create([
            'team_id' => $user->currentTeam->id,
            'name'    => 'DB',
            'type'    => 'database',
            'driver'  => 'sqlite',
            'config'  => [],
            'status'  => 'active',
        ]);

        $this->withToken($token)->postJson('/api/v1/sql/generate', [
            'question'     => 'hi',
            'connector_id' => $connector->id,
        ])->assertUnprocessable();
    }

    public function test_query_rejects_inactive_connector(): void
    {
        [$user, $token] = $this->actingAsUser();

        $connector = DataConnector::create([
            'team_id' => $user->currentTeam->id,
            'name'    => 'Inactive DB',
            'type'    => 'database',
            'driver'  => 'sqlite',
            'config'  => [],
            'status'  => 'inactive',
        ]);

        $this->withToken($token)->postJson('/api/v1/sql/query', [
            'question'     => 'Show me all records',
            'connector_id' => $connector->id,
        ])->assertUnprocessable();
    }

    public function test_query_requires_authentication(): void
    {
        $this->postJson('/api/v1/sql/query')->assertUnauthorized();
    }

    public function test_generate_requires_authentication(): void
    {
        $this->postJson('/api/v1/sql/generate')->assertUnauthorized();
    }
}
