<?php

namespace Tests\Feature\Metrics;

use App\Events\Analytics\BusinessDnaRecomputed;
use App\Jobs\Analytics\ComputeBusinessDnaJob;
use App\Jobs\Analytics\GenerateExecutiveBriefingJob;
use App\Livewire\Analytics\BusinessDnaPanel;
use App\Models\BusinessDnaProfile;
use App\Models\CrossPlatformInsight;
use App\Models\DataSource;
use App\Models\ExecutiveBriefing;
use App\Models\Team;
use App\Models\User;
use App\Services\BusinessDnaService;
use App\Services\IntelligenceEngineService;
use App\Services\Metrics\ComputeMetrics;
use App\Services\Metrics\MetricAnomalyDetector;
use App\Services\Metrics\SnapshotIngestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\Support\BuildsTeams;
use Tests\Support\FakeAiRouter;
use Tests\TestCase;

class BriefingAndDnaTest extends TestCase
{
    use BuildsTeams, RefreshDatabase;

    private const BASELINE_PROSE = [
        'Baseline pattern established',
        'Default risk profile',
        'Seasonal trends will emerge',
        'Monitoring for growth signals across',
    ];

    private User $owner;

    private Team $team;

    private string $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = $this->ownerWithTeam();
        $this->team = $this->owner->currentTeam;
        $this->company = $this->linkCompany($this->team);
        $this->travelTo(now()->setDate(2026, 10, 18)->setTime(9, 0));
    }

    private function connect(string $platform): DataSource
    {
        return DataSource::factory()->create(['team_id' => $this->team->id, 'platform' => $platform, 'status' => 'connected']);
    }

    private function push(DataSource $source, array $perDay): void
    {
        foreach ($perDay as $date => $metrics) {
            app(SnapshotIngestService::class)->ingest($source, $this->snapshotBody($this->company, $source->platform, $metrics, "{$date}T06:00:00Z"));
        }
        app(ComputeMetrics::class)->forTeam($this->team);
    }

    private function dna(FakeAiRouter $router): BusinessDnaService
    {
        return new BusinessDnaService(new IntelligenceEngineService, $router);
    }

    private function dnaJson(array $overrides = []): string
    {
        return json_encode(array_merge([
            'operational_patterns' => ['summary' => 'Backlog grows on Mondays.', 'key_patterns' => []],
            'seasonal_trends' => ['summary' => 'x', 'peaks' => [], 'troughs' => []],
            'risk_tolerance' => ['level' => 'low', 'notes' => 'x'],
            'growth_signals' => ['summary' => 'x', 'signals' => []],
        ], $overrides));
    }

    private function dataWithOverdue(): void
    {
        $this->push($this->connect('tasks'), ['2026-10-16' => ['overdue' => 11, 'open' => 100], '2026-10-17' => ['overdue' => 42, 'open' => 104]]);
    }

    // ─── Business DNA ────────────────────────────────────────────────────────

    public function test_dna_with_no_data_stores_nothing_and_asks_no_model(): void
    {
        $this->connect('tasks'); // connected, but never sent a snapshot
        $router = new FakeAiRouter(response: $this->dnaJson());

        $profile = $this->dna($router)->computeForTeam($this->team);

        $this->assertNull($profile);
        $this->assertSame(0, BusinessDnaProfile::withoutGlobalScopes()->count());
        $this->assertSame([], $router->prompts);
    }

    public function test_dna_with_nothing_connected_stores_nothing(): void
    {
        $this->assertNull($this->dna(new FakeAiRouter(response: $this->dnaJson()))->computeForTeam($this->team));
        $this->assertSame(0, BusinessDnaProfile::withoutGlobalScopes()->count());
    }

    public function test_dna_with_data_but_no_provider_stores_no_baseline_prose(): void
    {
        $this->dataWithOverdue();
        $router = new FakeAiRouter(configured: false);

        $this->assertNull($this->dna($router)->computeForTeam($this->team));
        $this->assertSame(0, BusinessDnaProfile::withoutGlobalScopes()->count());
    }

    public function test_dna_with_an_unparseable_answer_stores_nothing(): void
    {
        $this->dataWithOverdue();

        $this->assertNull($this->dna(new FakeAiRouter(response: 'not json'))->computeForTeam($this->team));
        $this->assertNull($this->dna(new FakeAiRouter(response: json_encode(['unrelated' => 1])))->computeForTeam($this->team));
        $this->assertSame(0, BusinessDnaProfile::withoutGlobalScopes()->count());
    }

    public function test_dna_with_data_is_built_from_the_models_answer_and_the_prompt_carries_the_real_metrics(): void
    {
        $this->dataWithOverdue();
        $router = new FakeAiRouter(response: $this->dnaJson());

        $profile = $this->dna($router)->computeForTeam($this->team);

        $this->assertNotNull($profile);
        $this->assertSame('Backlog grows on Mondays.', $profile->operational_patterns['summary']);
        $this->assertNull($profile->decision_patterns, 'a field the model did not return is empty, not filled with default prose');
        $this->assertNull($profile->bottlenecks);
        $this->assertGreaterThan(0, $profile->confidence_score);

        $prompt = $router->prompts[0];
        $this->assertStringContainsString('tasks.overdue', $prompt);
        $this->assertStringContainsString('= 42', $prompt);
        $this->assertStringContainsString('previous 11', $prompt);
        $this->assertStringContainsString('ONLY', $prompt);
    }

    public function test_a_failed_recompute_keeps_the_existing_profile_untouched(): void
    {
        $this->dataWithOverdue();
        $first = $this->dna(new FakeAiRouter(response: $this->dnaJson()))->computeForTeam($this->team);

        $again = $this->dna(new FakeAiRouter(configured: false))->computeForTeam($this->team);

        $this->assertSame($first->id, $again->id);
        $this->assertSame('Backlog grows on Mondays.', $again->operational_patterns['summary']);
        $this->assertSame(1, BusinessDnaProfile::withoutGlobalScopes()->count());
    }

    public function test_the_panel_says_no_data_yet_and_never_shows_structural_baseline_prose(): void
    {
        $this->connect('tasks');

        $component = Livewire::actingAs($this->owner)->test(BusinessDnaPanel::class)->assertSee('No data yet');
        foreach (self::BASELINE_PROSE as $prose) {
            $component->assertDontSee($prose);
        }
    }

    public function test_recomputing_with_no_data_says_nothing_to_compute_and_creates_no_profile(): void
    {
        $this->connect('tasks');
        app()->instance(BusinessDnaService::class, $this->dna(new FakeAiRouter(response: $this->dnaJson())));

        Livewire::actingAs($this->owner)->test(BusinessDnaPanel::class)
            ->call('compute')
            ->assertSee('Nothing to compute')
            ->assertSee('No data yet');

        $this->assertSame(0, BusinessDnaProfile::withoutGlobalScopes()->count());
    }

    public function test_recomputing_with_data_and_a_provider_shows_the_profile(): void
    {
        $this->dataWithOverdue();
        app()->instance(BusinessDnaService::class, $this->dna(new FakeAiRouter(response: $this->dnaJson())));

        Livewire::actingAs($this->owner)->test(BusinessDnaPanel::class)
            ->call('compute')
            ->assertSee('Backlog grows on Mondays.')
            ->assertDontSee('Nothing to compute');
    }

    public function test_recomputing_with_data_but_no_provider_says_why_and_stores_nothing(): void
    {
        $this->dataWithOverdue();
        app()->instance(BusinessDnaService::class, $this->dna(new FakeAiRouter(configured: false)));

        Livewire::actingAs($this->owner)->test(BusinessDnaPanel::class)
            ->call('compute')
            ->assertSee('could not be computed')
            ->assertSee('No data yet');

        $this->assertSame(0, BusinessDnaProfile::withoutGlobalScopes()->count());
    }

    public function test_the_recompute_command_reports_no_data_instead_of_a_confidence_for_it(): void
    {
        $this->connect('tasks');

        $this->artisan('analytics:recompute-dna', ['--team' => $this->team->id])
            ->expectsOutputToContain('no data yet')
            ->assertSuccessful();

        $this->assertSame(0, BusinessDnaProfile::withoutGlobalScopes()->count());
    }

    public function test_the_dna_job_announces_nothing_when_there_was_nothing_to_compute(): void
    {
        Event::fake([BusinessDnaRecomputed::class]);
        $this->connect('tasks');

        (new ComputeBusinessDnaJob($this->team->id))->handle($this->dna(new FakeAiRouter(response: $this->dnaJson())));

        Event::assertNotDispatched(BusinessDnaRecomputed::class);
    }

    public function test_legacy_baseline_only_profiles_are_removed_but_real_ones_are_kept(): void
    {
        $other = $this->ownerWithTeam()->currentTeam;
        BusinessDnaProfile::create([
            'team_id' => $this->team->id,
            'operational_patterns' => ['summary' => 'Baseline pattern established. More data needed for precise characterisation.', 'key_patterns' => []],
            'risk_tolerance' => ['level' => 'medium', 'notes' => 'Default risk profile. Will refine as decision data accumulates.'],
            'confidence_score' => 0.1,
        ]);
        BusinessDnaProfile::create([
            'team_id' => $other->id,
            'operational_patterns' => ['summary' => 'Backlog grows on Mondays.', 'key_patterns' => []],
            'confidence_score' => 0.5,
        ]);

        $migration = require database_path('migrations/2026_10_18_220000_remove_baseline_business_dna_profiles.php');
        $migration->up();

        $this->assertSame([$other->id], BusinessDnaProfile::withoutGlobalScopes()->pluck('team_id')->all());
    }

    // ─── Briefings ───────────────────────────────────────────────────────────

    private function briefing(FakeAiRouter $router): void
    {
        (new GenerateExecutiveBriefingJob($this->team->id, 'weekly', now()->toDateString()))
            ->handle($router, app(IntelligenceEngineService::class));
    }

    public function test_a_briefing_can_be_written_from_real_computed_metrics_alone(): void
    {
        $this->dataWithOverdue();
        $router = new FakeAiRouter(response: json_encode(['summary' => 'Overdue tasks quadrupled.', 'highlights' => [], 'risks' => ['Overdue tasks'], 'recommendations' => []]));

        $this->briefing($router);

        $briefing = ExecutiveBriefing::firstOrFail();
        $this->assertSame('ready', $briefing->status);
        $this->assertSame('Overdue tasks quadrupled.', $briefing->summary);
        $this->assertStringContainsString('tasks.overdue', $router->prompts[0]);
        $this->assertStringContainsString('= 42', $router->prompts[0]);
        $this->assertContains('tasks.overdue', $briefing->provenance['metric_keys']);
        $this->assertSame([], $briefing->provenance['insight_ids']);
    }

    public function test_the_briefing_prompt_marks_stale_and_no_data_metrics_and_includes_open_anomalies(): void
    {
        $source = $this->connect('tasks');
        $perDay = [];
        foreach ([10, 11, 9, 10, 12, 10, 8, 10, 40] as $i => $v) {
            $perDay[now()->utc()->startOfDay()->subDays(9 - $i)->format('Y-m-d')] = ['overdue' => $v];
        }
        $perDay['2026-10-10'] = ['overdue' => 10, 'created_7d' => 5];
        $this->push($source, $perDay);
        app(MetricAnomalyDetector::class)->detectForTeam($this->team);
        $router = new FakeAiRouter(response: json_encode(['summary' => 'Ok.']));

        $this->briefing($router);

        $prompt = $router->prompts[0];
        $this->assertStringContainsString('tasks.created_7d', $prompt);
        $this->assertStringContainsString('STALE', $prompt);
        $this->assertStringContainsString('tasks.completed_7d: no data yet', $prompt);
        $this->assertStringContainsString('Open anomaly alerts', $prompt);
        $this->assertStringContainsString('Anomaly detected: Overdue tasks', $prompt);
    }

    public function test_insights_and_metrics_are_both_offered_to_the_model(): void
    {
        $this->dataWithOverdue();
        $insight = CrossPlatformInsight::factory()->create(['team_id' => $this->team->id]);
        $router = new FakeAiRouter(response: json_encode(['summary' => 'Ok.']));

        $this->briefing($router);

        $this->assertStringContainsString($insight->title, $router->prompts[0]);
        $this->assertStringContainsString('tasks.overdue', $router->prompts[0]);
    }

    public function test_with_neither_metrics_nor_insights_there_is_still_no_briefing_and_no_model_call(): void
    {
        $this->connect('tasks');
        $router = new FakeAiRouter(response: json_encode(['summary' => 'made up']));

        $this->briefing($router);

        $this->assertSame('unavailable', ExecutiveBriefing::firstOrFail()->status);
        $this->assertSame([], $router->prompts);
    }

    public function test_one_teams_metrics_never_reach_another_teams_briefing(): void
    {
        $this->dataWithOverdue();
        $other = $this->ownerWithTeam()->currentTeam;
        $router = new FakeAiRouter(response: json_encode(['summary' => 'made up']));

        (new GenerateExecutiveBriefingJob($other->id, 'weekly', now()->toDateString()))->handle($router, app(IntelligenceEngineService::class));

        $this->assertSame([], $router->prompts);
    }
}
