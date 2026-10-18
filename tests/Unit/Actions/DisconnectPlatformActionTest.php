<?php

namespace Tests\Unit\Actions;

use App\Actions\Analytics\DisconnectPlatformAction;
use App\Models\DataSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisconnectPlatformActionTest extends TestCase
{
    use RefreshDatabase;

    private DisconnectPlatformAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new DisconnectPlatformAction;
    }

    public function test_disconnect_deletes_datasource_and_returns_name(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        DataSource::factory()->create([
            'team_id' => $team->id,
            'platform' => 'tasks',
            'display_name' => 'Dot.Fleet',
            'status' => 'connected',
        ]);

        $name = $this->action->handle($team, 'tasks');

        $this->assertEquals('Dot.Fleet', $name);
        $this->assertDatabaseMissing('data_sources', ['team_id' => $team->id, 'platform' => 'tasks']);
    }

    public function test_disconnect_returns_null_when_not_connected(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $name = $this->action->handle($user->currentTeam, 'tasks');

        $this->assertNull($name);
    }

    public function test_disconnect_only_removes_specified_platform(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        DataSource::factory()->create(['team_id' => $team->id, 'platform' => 'tasks', 'status' => 'connected']);
        DataSource::factory()->create(['team_id' => $team->id, 'platform' => 'emall',   'status' => 'connected']);

        $this->action->handle($team, 'tasks');

        $this->assertDatabaseHas('data_sources', ['team_id' => $team->id, 'platform' => 'emall']);
        $this->assertDatabaseMissing('data_sources', ['team_id' => $team->id, 'platform' => 'tasks']);
    }

    public function test_disconnect_does_not_affect_other_teams(): void
    {
        $userA = User::factory()->withPersonalTeam()->create();
        $userB = User::factory()->withPersonalTeam()->create();

        DataSource::factory()->create(['team_id' => $userA->currentTeam->id, 'platform' => 'tasks', 'status' => 'connected']);
        DataSource::factory()->create(['team_id' => $userB->currentTeam->id, 'platform' => 'tasks', 'status' => 'connected']);

        $this->action->handle($userA->currentTeam, 'tasks');

        $this->assertDatabaseHas('data_sources', ['team_id' => $userB->currentTeam->id, 'platform' => 'tasks']);
    }
}
