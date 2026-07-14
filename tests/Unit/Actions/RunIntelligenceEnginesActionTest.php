<?php

namespace Tests\Unit\Actions;

use App\Actions\Analytics\RunIntelligenceEnginesAction;
use App\Models\DataSource;
use App\Models\User;
use App\Services\IntelligenceEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RunIntelligenceEnginesActionTest extends TestCase
{
    use RefreshDatabase;

    private RunIntelligenceEnginesAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new RunIntelligenceEnginesAction(new IntelligenceEngineService());
    }

    private function teamWithPlatform(string $platform): \App\Models\Team
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        DataSource::factory()->create([
            'team_id'  => $team->id,
            'platform' => $platform,
            'status'   => 'connected',
        ]);

        return $team;
    }

    public function test_returns_zero_when_no_platforms_connected(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $count = $this->action->handle($user->currentTeam);

        $this->assertEquals(0, $count);
    }

    public function test_dispatches_jobs_for_active_engines(): void
    {
        Queue::fake();

        $team  = $this->teamWithPlatform('dot.fleet');
        $count = $this->action->handle($team);

        $this->assertGreaterThan(0, $count);
        Queue::assertPushed(\App\Jobs\Analytics\RunIntelligenceEngineJob::class);
    }

    public function test_dispatches_only_specified_engines(): void
    {
        Queue::fake();

        $team  = $this->teamWithPlatform('dot.hear');
        $count = $this->action->handle($team, ['community']);

        $this->assertEquals(1, $count);
        Queue::assertPushed(\App\Jobs\Analytics\RunIntelligenceEngineJob::class, 1);
    }

    public function test_returns_zero_for_engine_with_no_connected_platform(): void
    {
        Queue::fake();

        $user  = User::factory()->withPersonalTeam()->create();
        $count = $this->action->handle($user->currentTeam, ['community']);

        $this->assertEquals(0, $count);
        Queue::assertNothingPushed();
    }

    public function test_dispatches_multiple_engines_when_multiple_platforms_connected(): void
    {
        Queue::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        foreach (['dot.fleet', 'dot.hr', 'dot.crm'] as $p) {
            DataSource::factory()->create(['team_id' => $team->id, 'platform' => $p, 'status' => 'connected']);
        }

        $count = $this->action->handle($team);

        $this->assertGreaterThan(3, $count);
    }
}
