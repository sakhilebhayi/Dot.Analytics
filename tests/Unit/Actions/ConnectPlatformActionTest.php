<?php

namespace Tests\Unit\Actions;

use App\Actions\Analytics\ConnectPlatformAction;
use App\Models\DataSource;
use App\Models\User;
use App\Services\IntelligenceEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ConnectPlatformActionTest extends TestCase
{
    use RefreshDatabase;

    private ConnectPlatformAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new ConnectPlatformAction(new IntelligenceEngineService);
    }

    private function team()
    {
        return User::factory()->withPersonalTeam()->create()->currentTeam;
    }

    public function test_connect_creates_data_source_for_known_platform(): void
    {
        $team = $this->team();
        $source = $this->action->handle($team, 'dot.fleet');

        $this->assertInstanceOf(DataSource::class, $source);
        $this->assertEquals($team->id, $source->team_id);
        $this->assertEquals('dot.fleet', $source->platform);
        $this->assertEquals('connected', $source->status);
        $this->assertEquals('Dot.Fleet', $source->display_name);
    }

    public function test_connect_sets_capabilities_from_catalog(): void
    {
        $team = $this->team();
        $source = $this->action->handle($team, 'dot.crm');

        $this->assertIsArray($source->capabilities);
        $this->assertNotEmpty($source->capabilities);
    }

    public function test_connect_sets_base_url_when_provided(): void
    {
        $team = $this->team();
        $source = $this->action->handle($team, 'dot.fleet', 'https://fleet.infodot.app');

        $this->assertEquals('https://fleet.infodot.app', $source->base_url);
    }

    public function test_connect_stores_connected_at_timestamp(): void
    {
        $team = $this->team();
        $source = $this->action->handle($team, 'dot.hr');

        $this->assertNotNull($source->connected_at);
    }

    public function test_connect_throws_for_unknown_platform(): void
    {
        $this->expectException(ValidationException::class);

        $this->action->handle($this->team(), 'dot.nonexistent');
    }

    public function test_connect_updates_existing_source_idempotently(): void
    {
        $team = $this->team();
        $this->action->handle($team, 'dot.fleet');
        $this->action->handle($team, 'dot.fleet', 'https://updated-url.app');

        $this->assertDatabaseCount('data_sources', 1);
        $this->assertDatabaseHas('data_sources', ['base_url' => 'https://updated-url.app']);
    }

    public function test_connect_persists_to_database(): void
    {
        $team = $this->team();
        $this->action->handle($team, 'dot.payments');

        $this->assertDatabaseHas('data_sources', [
            'team_id' => $team->id,
            'platform' => 'dot.payments',
            'status' => 'connected',
        ]);
    }
}
