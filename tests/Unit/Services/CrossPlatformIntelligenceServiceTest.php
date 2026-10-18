<?php

namespace Tests\Unit\Services;

use App\Events\Analytics\CriticalInsightDiscovered;
use App\Events\Analytics\IntelligenceEngineCompleted;
use App\Models\AnalyticsSnapshot;
use App\Models\CrossPlatformInsight;
use App\Models\DataSource;
use App\Models\IntelligenceEngineRun;
use App\Models\Team;
use App\Models\User;
use App\Services\CrossPlatformIntelligenceService;
use App\Services\IntelligenceEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Support\FakeAiRouter;
use Tests\TestCase;

class CrossPlatformIntelligenceServiceTest extends TestCase
{
    use RefreshDatabase;

    private CrossPlatformIntelligenceService $service;

    private FakeAiRouter $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = new FakeAiRouter(response: json_encode([[
            'title' => 'Reviews are climbing',
            'narrative' => 'Dot.Hear logged 12 new reviews.',
            'platforms_involved' => ['dot.hear'],
            'insight_type' => 'correlation',
            'confidence' => 0.8,
            'severity' => 'warning',
        ]]));
        $this->service = new CrossPlatformIntelligenceService(new IntelligenceEngineService, $this->router);
    }

    /** Real ingested data: the only thing an insight may be built from. */
    private function withSnapshot(Team $team, string $platform): void
    {
        $source = DataSource::withoutGlobalScopes()->where('team_id', $team->id)->where('platform', $platform)->firstOrFail();
        AnalyticsSnapshot::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'data_source_id' => $source->id,
            'snapshot_type' => 'webhook',
            'payload' => ['reviews' => 12],
            'captured_at' => now(),
        ]);
    }

    private function teamWithPlatforms(array $platforms): Team
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        foreach ($platforms as $platform) {
            DataSource::factory()->create([
                'team_id' => $team->id,
                'platform' => $platform,
                'status' => 'connected',
            ]);
        }

        return $team;
    }

    // ─── runForTeam ─────────────────────────────────────────────────────────

    public function test_run_for_team_returns_zero_when_no_platforms(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $count = $this->service->runForTeam($user->currentTeam);

        $this->assertEquals(0, $count);
    }

    public function test_run_for_team_records_no_data_run_when_nothing_was_ingested(): void
    {
        $team = $this->teamWithPlatforms(['dot.hear']); // community engine, no snapshots

        $count = $this->service->runForTeam($team);

        $this->assertSame(0, $count);
        $this->assertDatabaseHas('intelligence_engine_runs', [
            'team_id' => $team->id,
            'engine' => 'community',
            'status' => 'no_data',
        ]);
        $this->assertDatabaseMissing('cross_platform_insights', ['team_id' => $team->id]);
    }

    public function test_run_for_team_creates_completed_engine_run_records_from_real_data(): void
    {
        $team = $this->teamWithPlatforms(['dot.hear']);
        $this->withSnapshot($team, 'dot.hear');

        $this->service->runForTeam($team);

        $this->assertDatabaseHas('intelligence_engine_runs', [
            'team_id' => $team->id,
            'engine' => 'community',
            'status' => 'completed',
        ]);
    }

    public function test_run_for_team_persists_insights(): void
    {
        $team = $this->teamWithPlatforms(['dot.hear']);
        $this->withSnapshot($team, 'dot.hear');

        $count = $this->service->runForTeam($team);

        $this->assertGreaterThan(0, $count);
        $this->assertDatabaseHas('cross_platform_insights', ['team_id' => $team->id]);
    }

    public function test_run_for_team_fires_completed_event(): void
    {
        Event::fake([IntelligenceEngineCompleted::class]);

        $team = $this->teamWithPlatforms(['dot.hear']);
        $this->withSnapshot($team, 'dot.hear');
        $this->service->runForTeam($team);

        Event::assertDispatched(IntelligenceEngineCompleted::class);
    }

    public function test_run_for_team_fires_critical_insight_event_for_critical_insights(): void
    {
        Event::fake([CriticalInsightDiscovered::class]);
        $this->router->response = json_encode([[
            'title' => 'Review sentiment collapsing',
            'narrative' => 'Dot.Hear logged 12 new reviews, mostly negative.',
            'platforms_involved' => ['dot.hear'],
            'insight_type' => 'risk',
            'confidence' => 0.9,
            'severity' => 'critical',
        ]]);
        $team = $this->teamWithPlatforms(['dot.hear']);
        $this->withSnapshot($team, 'dot.hear');

        $this->service->runForTeam($team);

        Event::assertDispatched(CriticalInsightDiscovered::class);
        $this->assertGreaterThan(0, CrossPlatformInsight::where('team_id', $team->id)->where('severity', 'critical')->count());
    }

    public function test_run_for_team_never_marks_run_failed_when_the_provider_is_simply_absent(): void
    {
        $team = $this->teamWithPlatforms(['dot.hear']);
        $this->withSnapshot($team, 'dot.hear');
        $this->router->configured = false;

        $count = $this->service->runForTeam($team);

        $this->assertDatabaseMissing('intelligence_engine_runs', [
            'team_id' => $team->id,
            'status' => 'failed',
        ]);
    }

    public function test_run_for_team_with_multiple_platforms_activates_more_engines(): void
    {
        $singlePlatformTeam = $this->teamWithPlatforms(['dot.hear']);
        $multiPlatformTeam = $this->teamWithPlatforms(['dot.fleet', 'dot.crm', 'dot.hr', 'dot.payments', 'dot.hear']);

        $this->service->runForTeam($singlePlatformTeam);
        $this->service->runForTeam($multiPlatformTeam);

        $singleCount = IntelligenceEngineRun::where('team_id', $singlePlatformTeam->id)->count();
        $multiCount = IntelligenceEngineRun::where('team_id', $multiPlatformTeam->id)->count();

        $this->assertGreaterThan($singleCount, $multiCount);
    }

    public function test_insights_have_required_fields(): void
    {
        $team = $this->teamWithPlatforms(['dot.hear']);
        $this->withSnapshot($team, 'dot.hear');
        $this->service->runForTeam($team);

        $insight = CrossPlatformInsight::where('team_id', $team->id)->first();

        $this->assertNotNull($insight->title);
        $this->assertNotNull($insight->narrative);
        $this->assertNotEmpty($insight->platforms_involved);
        $this->assertNotNull($insight->insight_type);
        $this->assertGreaterThan(0, $insight->confidence);
        $this->assertContains($insight->severity, ['info', 'warning', 'critical']);
    }

    public function test_insights_are_team_scoped(): void
    {
        $teamA = $this->teamWithPlatforms(['dot.hear']);
        $this->withSnapshot($teamA, 'dot.hear');
        // Team B has NO connected platforms — no engines will run for it
        $teamB = User::factory()->withPersonalTeam()->create()->currentTeam;

        $this->service->runForTeam($teamA);

        $this->assertDatabaseMissing('cross_platform_insights', ['team_id' => $teamB->id]);
    }

    public function test_engine_run_records_platforms_consumed(): void
    {
        $team = $this->teamWithPlatforms(['dot.hear']);
        $this->service->runForTeam($team);

        $run = IntelligenceEngineRun::where('team_id', $team->id)->first();
        $this->assertNotEmpty($run->platforms_consumed);
        $this->assertContains('dot.hear', $run->platforms_consumed);
    }
}
