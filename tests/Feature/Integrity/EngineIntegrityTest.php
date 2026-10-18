<?php

namespace Tests\Feature\Integrity;

use App\Events\Analytics\CriticalInsightDiscovered;
use App\Events\Analytics\PlatformConnected;
use App\Jobs\Analytics\RunIntelligenceEngineJob;
use App\Models\AnalyticsAlert;
use App\Models\CrossPlatformInsight;
use App\Models\IntelligenceEngineRun;
use App\Services\AiModelRouter;
use App\Services\CrossPlatformIntelligenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Support\BuildsTeams;
use Tests\Support\FakeAiRouter;
use Tests\TestCase;

/**
 * Insights must come from the team's real ingested snapshots or not exist at all.
 */
class EngineIntegrityTest extends TestCase
{
    use BuildsTeams, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Connecting a platform normally queues engine runs; keep the fixtures inert
        // so each test controls exactly when (and with what data) an engine runs.
        Event::fake([PlatformConnected::class]);
    }

    private function useRouter(FakeAiRouter $router): FakeAiRouter
    {
        $this->app->instance(AiModelRouter::class, $router);
        $this->app->forgetInstance(CrossPlatformIntelligenceService::class);

        return $router;
    }

    private function runJob(int $teamId, string $engine): void
    {
        app()->call([new RunIntelligenceEngineJob($teamId, $engine), 'handle']);
    }

    private function insightJson(array $overrides = []): string
    {
        return json_encode([array_merge([
            'title' => 'Idle time is rising on fleet',
            'narrative' => 'Vehicle V-001 logged 7.5 idle hours.',
            'platforms_involved' => ['dot.fleet'],
            'insight_type' => 'correlation',
            'confidence' => 0.8,
            'severity' => 'warning',
        ], $overrides)]);
    }

    public function test_no_provider_records_skipped_run_and_saves_no_insight_or_alert(): void
    {
        $team = $this->ownerWithTeam()->currentTeam;
        $this->snapshot($this->connect($team, 'dot.hear'), ['reviews' => 12]);
        $router = $this->useRouter(new FakeAiRouter(configured: false));

        $this->runJob($team->id, 'community');

        $run = IntelligenceEngineRun::where('team_id', $team->id)->firstOrFail();
        $this->assertSame('skipped', $run->status);
        $this->assertStringContainsStringIgnoringCase('provider', (string) $run->status_reason);
        $this->assertSame(0, $run->insights_generated);
        $this->assertSame(0, CrossPlatformInsight::count());
        $this->assertSame(0, AnalyticsAlert::count());
        $this->assertSame([], $router->prompts);
    }

    public function test_no_snapshots_records_no_data_and_never_calls_the_model(): void
    {
        $team = $this->ownerWithTeam()->currentTeam;
        $this->connect($team, 'dot.hear');
        $router = $this->useRouter(new FakeAiRouter(response: $this->insightJson()));

        $this->runJob($team->id, 'community');

        $run = IntelligenceEngineRun::where('team_id', $team->id)->firstOrFail();
        $this->assertSame('no_data', $run->status);
        $this->assertNotEmpty($run->status_reason);
        $this->assertSame([], $router->prompts);
        $this->assertSame(0, CrossPlatformInsight::count());
    }

    public function test_prompt_is_built_from_real_snapshots_and_platforms_without_data_are_skipped(): void
    {
        $team = $this->ownerWithTeam()->currentTeam;
        $fleet = $this->connect($team, 'dot.fleet');
        $this->connect($team, 'dot.hr'); // connected but never pushed anything
        $snapshot = $this->snapshot($fleet, ['vehicles' => [['id' => 'V-001', 'idle_hours' => 7.5]]]);
        $router = $this->useRouter(new FakeAiRouter(response: $this->insightJson([
            'platforms_involved' => ['dot.fleet', 'dot.hr'],
        ])));

        $this->runJob($team->id, 'operational');

        $this->assertCount(1, $router->prompts);
        $this->assertStringContainsString('V-001', $router->prompts[0]);
        $this->assertStringContainsString('7.5', $router->prompts[0]);
        $this->assertStringContainsString('dot.hr', $router->prompts[0]); // named only as "no data"
        $this->assertStringContainsStringIgnoringCase('no snapshots', $router->prompts[0]);

        $run = IntelligenceEngineRun::where('team_id', $team->id)->firstOrFail();
        $this->assertSame('completed', $run->status);
        $this->assertSame(['dot.hr'], $run->provenance['platforms_skipped']);
        $this->assertSame([$snapshot->id], $run->provenance['snapshot_ids']);

        $insight = CrossPlatformInsight::firstOrFail();
        $this->assertSame(['dot.fleet'], $insight->platforms_involved, 'platforms with no data may not be cited');
        $this->assertSame([$snapshot->id], $insight->provenance['snapshot_ids']);
        $this->assertArrayHasKey('window_start', $insight->provenance);
        $this->assertArrayHasKey('window_end', $insight->provenance);
        $this->assertSame($run->id, $insight->provenance['engine_run_id']);
    }

    public function test_snapshots_outside_the_window_do_not_count(): void
    {
        $team = $this->ownerWithTeam()->currentTeam;
        $this->snapshot($this->connect($team, 'dot.hear'), ['reviews' => 3], now()->subDays(90));
        $router = $this->useRouter(new FakeAiRouter);

        $this->runJob($team->id, 'community');

        $this->assertSame('no_data', IntelligenceEngineRun::firstOrFail()->status);
        $this->assertSame([], $router->prompts);
    }

    public function test_snapshot_summary_is_size_capped(): void
    {
        $team = $this->ownerWithTeam()->currentTeam;
        $source = $this->connect($team, 'dot.hear');
        for ($i = 0; $i < 12; $i++) {
            $this->snapshot($source, ['blob' => str_repeat('x', 100_000), 'n' => $i]);
        }
        $router = $this->useRouter(new FakeAiRouter(response: '[]'));

        $this->runJob($team->id, 'community');

        $this->assertLessThan(20_000, strlen($router->prompts[0]));
    }

    public function test_insight_citing_only_platforms_without_data_is_dropped(): void
    {
        $team = $this->ownerWithTeam()->currentTeam;
        $this->snapshot($this->connect($team, 'dot.hear'), ['reviews' => 3]);
        $this->useRouter(new FakeAiRouter(response: $this->insightJson(['platforms_involved' => ['dot.crm']])));

        $this->runJob($team->id, 'community');

        $this->assertSame(0, CrossPlatformInsight::count());
    }

    public function test_unparseable_model_output_fails_the_run_and_saves_nothing(): void
    {
        $team = $this->ownerWithTeam()->currentTeam;
        $this->snapshot($this->connect($team, 'dot.hear'), ['reviews' => 3]);
        $this->useRouter(new FakeAiRouter(response: 'Sorry, I cannot help with that.'));

        $this->runJob($team->id, 'community');

        $run = IntelligenceEngineRun::firstOrFail();
        $this->assertSame('failed', $run->status);
        $this->assertNotEmpty($run->status_reason);
        $this->assertSame(0, CrossPlatformInsight::count());
    }

    public function test_provider_failure_marks_run_failed_without_canned_text(): void
    {
        $team = $this->ownerWithTeam()->currentTeam;
        $this->snapshot($this->connect($team, 'dot.hear'), ['reviews' => 3]);
        $this->useRouter(new FakeAiRouter(configured: true, response: null));

        $this->runJob($team->id, 'community');

        $this->assertSame('failed', IntelligenceEngineRun::firstOrFail()->status);
        $this->assertSame(0, CrossPlatformInsight::count());
    }

    public function test_critical_insight_from_real_data_creates_an_alert(): void
    {
        $team = $this->ownerWithTeam()->currentTeam;
        $this->snapshot($this->connect($team, 'dot.hear'), ['reviews' => 3]);
        $this->useRouter(new FakeAiRouter(response: $this->insightJson([
            'platforms_involved' => ['dot.hear'],
            'severity' => 'critical',
        ])));

        $this->runJob($team->id, 'community');

        $this->assertSame(1, CrossPlatformInsight::where('severity', 'critical')->count());
        $this->assertSame(1, AnalyticsAlert::count());
    }

    public function test_each_listener_is_registered_exactly_once(): void
    {
        $listeners = app('events')->getListeners(CriticalInsightDiscovered::class);

        $this->assertCount(1, $listeners);
    }

    public function test_service_run_for_team_without_provider_persists_nothing(): void
    {
        $team = $this->ownerWithTeam()->currentTeam;
        $this->snapshot($this->connect($team, 'dot.hear'), ['reviews' => 3]);
        $this->useRouter(new FakeAiRouter(configured: false));

        $count = app(CrossPlatformIntelligenceService::class)->runForTeam($team);

        $this->assertSame(0, $count);
        $this->assertSame(0, CrossPlatformInsight::count());
        $this->assertSame(0, AnalyticsAlert::count());
        $this->assertSame('skipped', IntelligenceEngineRun::firstOrFail()->status);
    }

    public function test_service_run_for_team_uses_snapshots_and_stamps_provenance(): void
    {
        $team = $this->ownerWithTeam()->currentTeam;
        $snapshot = $this->snapshot($this->connect($team, 'dot.hear'), ['reviews' => 17]);
        $router = $this->useRouter(new FakeAiRouter(response: $this->insightJson([
            'platforms_involved' => ['dot.hear'],
        ])));

        $count = app(CrossPlatformIntelligenceService::class)->runForTeam($team);

        // dot.hear feeds several engines; each one analyses the same real snapshot.
        $this->assertGreaterThan(0, $count);
        $this->assertSame($count, CrossPlatformInsight::count());
        $this->assertStringContainsString('17', $router->prompts[0]);
        foreach (CrossPlatformInsight::all() as $insight) {
            $this->assertSame([$snapshot->id], $insight->provenance['snapshot_ids']);
        }
    }

    public function test_no_canned_fallback_text_exists_in_the_code_base(): void
    {
        foreach ([
            'app/Jobs/Analytics/RunIntelligenceEngineJob.php',
            'app/Services/CrossPlatformIntelligenceService.php',
        ] as $file) {
            $source = file_get_contents(base_path($file));
            $this->assertStringNotContainsString('three certified operators', $source, $file);
            $this->assertStringNotContainsString('Supplier concentration risk', $source, $file);
            $this->assertStringNotContainsString('fallbackInsights', $source, $file);
            $this->assertStringNotContainsString('function fallback', $source, $file);
        }
    }
}
