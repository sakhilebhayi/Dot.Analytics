<?php

namespace Tests\Feature;

use App\Http\Controllers\WorkspaceController;
use App\Livewire\Analytics\BusinessPlatformsPanel;
use App\Models\AnalyticsAlert;
use App\Models\DataSource;
use App\Models\Recommendation;
use App\Models\User;
use App\Services\AiModelRouter;
use App\Services\Metrics\MetricCatalogue;
use App\Services\Metrics\SnapshotIngestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BuildsTeams;
use Tests\TestCase;

/**
 * The page a person lands on after signing in through InfoDot: the real logo, a welcome from real records, one dominant
 * action that leads somewhere that works for that person, and no figure that a platform did not send.
 */
class LandingExperienceTest extends TestCase
{
    use BuildsTeams, RefreshDatabase;

    /** The original artwork. If either changes, someone has altered a logo. */
    private const LOGOS = [
        'images/logo.png' => '4bc6492a45bcbc727f21d1273c7f9fe3ebb6107e34148fa0d33df839d45688dd',
        'images/logo-light.png' => '0554837b8848466a127241fdc2021f4806f4a96d1348c4dc9712a7294e8c04ef',
    ];

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ecosystem.sso_only' => false, 'services.anthropic.key' => null]);

        $this->owner = User::factory()->withPersonalTeam()->create(['name' => 'Thandi Mokoena']);
        $this->owner->currentTeam->forceFill(['name' => 'Karoo Freight'])->save();
        $this->linkCompany($this->owner->currentTeam);
    }

    private function person(string $role): User
    {
        return $role === 'owner' ? $this->owner : $this->memberOf($this->owner, $role);
    }

    /** Connects a platform and sends real snapshots through the real pipeline. @param array<string, array<string, int>> $days */
    private function report(string $platform, array $days): void
    {
        $team = $this->owner->currentTeam;
        $source = DataSource::factory()->create(['team_id' => $team->id, 'platform' => $platform, 'status' => 'connected']);

        foreach ($days as $day => $metrics) {
            app(SnapshotIngestService::class)->ingest($source, $this->snapshotBody($team->infodot_team_uuid, $platform, $metrics, $day.'T06:00:00Z'));
        }

        Artisan::call('analytics:compute-metrics');
    }

    private function yesterday(int $daysAgo = 1): string
    {
        return now()->utc()->startOfDay()->subDays($daysAgo)->format('Y-m-d');
    }

    /** @return array{0: string, 1: string} the primary action's label and address */
    private function primaryAction(string $html): array
    {
        $this->assertSame(1, substr_count($html, 'data-primary-action'), 'exactly one primary action');
        $this->assertSame(1, preg_match('/<a class="primary" data-primary-action href="([^"]+)">([^<]+)<\/a>/', $html, $m));

        return [trim($m[2]), $m[1]];
    }

    private function tape(string $html): string
    {
        $this->assertSame(1, preg_match('/<section class="stage".*?<\/section>/s', $html, $m));

        return $m[0];
    }

    /** @return array<string, array{0: string, 1: string, 2: string}> */
    public static function arrivalsWithNothingConnected(): array
    {
        return [
            'owner' => ['owner', 'Connect a platform', 'workspace.sources'],
            'admin' => ['admin', 'Connect a platform', 'workspace.sources'],
            'editor' => ['editor', 'Read the public library', 'intel.home'],
            'viewer' => ['viewer', 'Read the public library', 'intel.home'],
        ];
    }

    #[DataProvider('arrivalsWithNothingConnected')]
    public function test_with_nothing_connected_each_role_gets_one_action_that_works_for_it(string $role, string $label, string $route): void
    {
        $person = $this->person($role);

        $html = $this->actingAs($person)->get('/dashboard')->assertOk()->getContent();

        $this->assertSame([$label, route($route)], $this->primaryAction($html));
        $this->actingAs($person)->get(route($route))->assertOk();
    }

    /** @return array<string, array{0: string, 1: bool, 2: string, 3: string}> */
    public static function arrivalsWithFigures(): array
    {
        return [
            'owner, assistant configured' => ['owner', true, 'Ask a question', 'workspace.ask'],
            'editor, assistant configured' => ['editor', true, 'Ask a question', 'workspace.ask'],
            'viewer may not ask' => ['viewer', true, 'Read the figures', 'workspace.figures'],
            'owner, no assistant configured' => ['owner', false, 'Read the figures', 'workspace.figures'],
            'admin, no assistant configured' => ['admin', false, 'Read the figures', 'workspace.figures'],
        ];
    }

    #[DataProvider('arrivalsWithFigures')]
    public function test_with_figures_each_role_gets_one_action_that_works_for_it(string $role, bool $assistant, string $label, string $route): void
    {
        $this->report('tasks', [$this->yesterday() => ['open' => 122, 'overdue' => 12]]);
        $this->mock(AiModelRouter::class, fn ($mock) => $mock->shouldReceive('hasProvider')->andReturn($assistant));
        $person = $this->person($role);

        $html = $this->actingAs($person)->get('/dashboard')->assertOk()->getContent();

        $this->assertSame([$label, route($route)], $this->primaryAction($html));
        $this->actingAs($person)->get(route($route))->assertOk();
    }

    public function test_a_connected_platform_that_has_sent_nothing_is_said_plainly_and_shows_no_figure(): void
    {
        $this->connect($this->owner->currentTeam, 'hr');

        $html = $this->actingAs($this->owner)->get('/dashboard')->assertOk()->assertSee('Nothing has posted yet')->getContent();

        $this->assertSame(['Check your sources', route('workspace.sources')], $this->primaryAction($html));
        $this->assertStringContainsString('Dot.HR is connected as a data source and has not sent a figure', $this->tape($html));
        $this->assertDoesNotMatchRegularExpression('/\d/', strip_tags($this->tape($html)), 'no number without a figure behind it');
    }

    public function test_the_landing_shows_the_original_logo_and_the_files_are_untouched(): void
    {
        foreach (self::LOGOS as $file => $hash) {
            $this->assertSame($hash, hash_file('sha256', public_path($file)), $file.' is not the original artwork');
        }

        $this->actingAs($this->owner)->get('/dashboard')->assertOk()
            ->assertSee('src="'.asset('images/logo.png').'" alt="Dot.Analytics"', false)
            ->assertSee('srcset="'.asset('images/logo-light.png').'" media="(prefers-color-scheme: dark)"', false);
    }

    public function test_the_headline_states_what_is_true_now_and_the_page_has_one_h1(): void
    {
        $html = $this->actingAs($this->owner)->get('/dashboard')->assertOk()
            ->assertSee('Nothing is reporting yet.')
            ->assertDontSee('Every figure shows its working.')
            ->assertDontSee('Welcome, Thandi')
            ->getContent();
        $this->assertSame(1, substr_count($html, '<h1'));

        $this->report('tasks', [$this->yesterday() => ['open' => 122, 'overdue' => 12]]);
        $this->actingAs($this->owner)->get('/dashboard')->assertOk()->assertSee('Everything reporting is current.')
            ->assertSee('Karoo Freight: 1 of 11 platforms that can report is reporting.');
    }

    public function test_the_way_back_to_infodot_is_in_the_same_words_on_every_platform(): void
    {
        config(['ecosystem.platforms.infodot.url' => 'https://infodot.co.za']);

        $this->actingAs($this->owner)->get('/dashboard')->assertOk()
            ->assertSee('<a href="https://infodot.co.za/home">InfoDot</a>', false)
            ->assertSee('<a href="https://infodot.co.za/platforms">Platforms</a>', false);
    }

    public function test_the_empty_state_prints_no_number_at_all(): void
    {
        $html = $this->actingAs($this->owner)->get('/dashboard')->assertOk()
            ->assertSee('No figures yet')
            ->assertSee('Nothing is waiting.')
            ->getContent();

        $this->assertSame(1, preg_match('/<div class="ld" data-state="nothing-connected">.*<\/div>\s*<\/main>/s', $html, $m));
        $this->assertDoesNotMatchRegularExpression('/\d/', strip_tags($m[0]), 'a company with no data sees no figure, not even a zero');
    }

    public function test_every_number_on_the_landing_comes_from_a_stored_record_and_names_its_source(): void
    {
        $this->report('tasks', [
            $this->yesterday(2) => ['open' => 121, 'overdue' => 14],
            $this->yesterday() => ['open' => 122, 'overdue' => 12],
        ]);
        $this->report('hr', [$this->yesterday() => ['headcount' => 64]]);
        $this->connect($this->owner->currentTeam, 'emall');
        AnalyticsAlert::factory()->count(2)->create(['team_id' => $this->owner->currentTeam->id, 'status' => 'open', 'title' => 'Overdue tasks are above their usual range']);
        Recommendation::factory()->create(['team_id' => $this->owner->currentTeam->id, 'status' => 'pending', 'title' => 'Review the oldest leave requests']);

        $response = $this->actingAs($this->owner)->get('/dashboard')->assertOk()->assertViewHas('connectedCount', 3);
        $html = $response->getContent();
        $rows = $this->ledgerRows($html);

        // One row from each reporting platform first, each with its platform, value and change.
        $this->assertSame(['tasks.open', 'hr.headcount', 'tasks.overdue'], array_keys($rows));
        $this->assertStringContainsString('Open tasks Dot.Tasks', $rows['tasks.open']);
        $this->assertStringContainsString('122', $rows['tasks.open']);
        $this->assertStringContainsString('Up 1', $rows['tasks.open']);
        $this->assertStringContainsString('Headcount Dot.HR', $rows['hr.headcount']);
        $this->assertStringContainsString('64 First report', $rows['hr.headcount']);
        $this->assertStringContainsString('Down 2', $rows['tasks.overdue']);

        // The stage shows the first row's working: sender, day, arrival, the report before.
        $stage = preg_replace('/\s+/', ' ', strip_tags(preg_replace('/<\/(dt|dd|td|th)>/', ' ', $this->tape($html))));
        $day = now()->utc()->startOfDay()->subDay()->format('j M Y');
        $this->assertStringContainsString('Open tasks 122 Up 1 (+0.8%) on the report before.', $stage);
        $this->assertStringContainsString('Sent by Dot.Tasks For the day '.$day.' Received', $stage);
        $this->assertStringContainsString('Report before 121 on '.now()->utc()->startOfDay()->subDays(2)->format('j M Y'), $stage);

        $more = preg_replace('/\s+/', ' ', strip_tags($this->sourcesCount($html)));
        $this->assertStringContainsString('2 of 11 platforms that can report are reporting data: Dot.Tasks and Dot.HR. Dot.Emall is connected and has not sent a figure yet. Counted from this company\'s data sources.', $more);

        $response->assertSee('2 alerts open')->assertSee('Overdue tasks are above their usual range')
            ->assertSee('1 recommended action')->assertSee('Review the oldest leave requests')
            ->assertDontSee('Nothing is waiting.');
    }

    public function test_the_ledger_is_capped_at_twelve_rows_and_says_how_many_figures_exist(): void
    {
        $this->seedEveryFigure();

        $html = $this->actingAs($this->owner)->get('/dashboard')->assertOk()->getContent();

        $this->assertCount(WorkspaceController::LEDGER_ROWS, $this->ledgerRows($html));
        $this->assertSame(12, WorkspaceController::LEDGER_ROWS);
        $this->assertMatchesRegularExpression('/All 1[3-9] figures|All [2-9]\d figures/', strip_tags($html));
    }

    public function test_the_selected_figure_is_validated_against_the_rows_listed(): void
    {
        $this->report('tasks', [$this->yesterday(2) => ['open' => 121, 'overdue' => 14], $this->yesterday() => ['open' => 122, 'overdue' => 12]]);
        $this->report('hr', [$this->yesterday() => ['headcount' => 64]]);

        // A listed figure is selected, and the stage and the chart are about it.
        $html = $this->actingAs($this->owner)->get('/dashboard?figure=hr.headcount')->assertOk()->getContent();
        $this->assertSame('hr.headcount', $this->selectedKey($html));
        $this->assertStringContainsString('<h2 id="ld-stage-title">Headcount</h2>', $html);
        $this->assertSame(1, substr_count($html, 'data-chart'));

        // Unknown, malformed, array-shaped and not-reporting keys fall back to the default instead of erroring.
        foreach (['nope.nothing', 'hr.<script>', 'emall.orders_7d', '', 'tasks.overdue%00'] as $bad) {
            $html = $this->actingAs($this->owner)->get('/dashboard?figure='.rawurlencode($bad))->assertOk()->getContent();
            $this->assertSame('tasks.open', $this->selectedKey($html), $bad);
            $this->assertStringNotContainsString('<script>', explode('</main>', $html)[0]);
        }
        $this->actingAs($this->owner)->get('/dashboard?figure[]=hr.headcount')->assertOk();

        // With an open anomaly alert, the figure it is about is the default.
        AnalyticsAlert::factory()->create(['team_id' => $this->owner->currentTeam->id, 'status' => 'open', 'dedupe_key' => 'anomaly:tasks.overdue:t', 'context' => ['metric_key' => 'tasks.overdue', 'period_date' => $this->yesterday()]]);
        $html = $this->actingAs($this->owner)->get('/dashboard')->assertOk()->getContent();
        $this->assertSame('tasks.overdue', $this->selectedKey($html));
    }

    public function test_another_companys_figure_cannot_be_selected_by_key_and_its_series_is_not_read(): void
    {
        $this->report('tasks', [$this->yesterday() => ['open' => 122]]);
        $stranger = $this->ownerWithTeam();
        $this->linkCompany($stranger->currentTeam);
        $this->connect($stranger->currentTeam, 'hr');

        $html = $this->actingAs($stranger)->get('/dashboard?figure=tasks.open')->assertOk()
            ->assertDontSee('Open tasks')->assertDontSee('Dot.Tasks')->getContent();
        $this->assertSame([], $this->ledgerRows($html));
        $this->assertNull($this->selectedKey($html));
        $this->assertSame(0, substr_count($html, 'data-chart'));
    }

    public function test_the_overview_reads_one_series_and_the_chart_script_is_a_built_asset(): void
    {
        $this->seedEveryFigure();
        $html = $this->actingAs($this->owner)->get('/dashboard')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'data-chart'), 'one chart, for the selected figure only');
        $this->assertSame(1, preg_match_all('/<polyline class="line"/', $html), 'one series drawn');
        $this->assertSame(1, preg_match('/<div class="ld" .*<\/div>\s*<\/main>/s', $html, $page));
        $this->assertStringNotContainsString('style="', $page[0]);
        $this->assertSame(0, preg_match('/\son[a-z]+="/', $page[0]));
        $this->assertFileExists(resource_path('js/dashboard.js'));
        $this->assertStringContainsString("'resources/js/dashboard.js'", file_get_contents(base_path('vite.config.js')));
        $this->assertStringContainsString("@vite(['resources/js/dashboard.js'])", file_get_contents(resource_path('views/dashboard.blade.php')));
        $this->assertStringNotContainsString('<script', $page[0], 'no script is written into the page body');
    }

    public function test_a_stale_figure_keeps_its_value_and_is_marked_out_of_date(): void
    {
        $this->report('hr', [$this->yesterday(6) => ['headcount' => 64]]);

        $html = $this->actingAs($this->owner)->get('/dashboard')->assertOk()->assertSee('Out of date')->getContent();

        $this->assertStringContainsString('64', $this->ledgerRows($html)['hr.headcount']);
        $this->assertStringContainsString('is-stale', $html);
        $this->assertStringContainsString('1 out of date', preg_replace('/\s+/', ' ', strip_tags($html)));
    }

    public function test_the_lower_column_fills_the_width_when_only_alerts_or_only_actions_exist(): void
    {
        $this->report('tasks', [$this->yesterday() => ['open' => 122]]);
        Recommendation::factory()->create(['team_id' => $this->owner->currentTeam->id, 'status' => 'pending', 'title' => 'Review the oldest leave requests']);

        $html = $this->actingAs($this->owner)->get('/dashboard')->assertOk()->assertSee('1 recommended action')->assertDontSee('alert open')->getContent();

        $this->assertSame(1, substr_count($html, '<ul class="rows">'));
    }

    /** @return array<string, string> data-figure => the row's text */
    private function ledgerRows(string $html): array
    {
        preg_match_all('/<tr data-figure="([^"]+)".*?<\/tr>/s', $html, $m, PREG_SET_ORDER);

        return collect($m)->mapWithKeys(fn ($r) => [$r[1] => trim(preg_replace('/\s+/', ' ', strip_tags(str_replace('<small>', ' <small>', $r[0]))))])->all();
    }

    private function sourcesCount(string $html): string
    {
        $this->assertSame(1, preg_match('/<p class="more" data-testid="landing-sources-count">.*?<\/p>/s', $html, $m));

        return $m[0];
    }

    private function selectedKey(string $html): ?string
    {
        return preg_match('/<tr data-figure="([^"]+)"[^>]*aria-current="true"/', $html, $m) ? $m[1] : null;
    }

    /** Four platforms, every figure each can report, so the ledger has more than twelve candidates. */
    private function seedEveryFigure(): void
    {
        $catalogue = new MetricCatalogue;
        foreach (['tasks', 'auction', 'hr', 'emall'] as $platform) {
            $metrics = collect(array_keys($catalogue->metrics($platform)))->mapWithKeys(fn ($k) => [$k => 10])->all();
            $metrics = collect($metrics)->map(fn ($v, $k) => $catalogue->isMoney($platform, $k) ? 10000 : $v)->all();
            if (collect(array_keys($metrics))->contains(fn ($k) => $catalogue->isMoney($platform, $k))) {
                $metrics['currency'] = 'ZAR';
            }
            $this->report($platform, [$this->yesterday(2) => $metrics, $this->yesterday() => $metrics]);
        }
    }

    public function test_another_companys_figures_never_reach_this_landing(): void
    {
        $this->report('tasks', [$this->yesterday() => ['open' => 122]]);
        $stranger = $this->ownerWithTeam();

        $this->actingAs($stranger)->get('/dashboard')->assertOk()->assertSee('No figures yet')->assertDontSee('122')->assertDontSee('Karoo Freight');
    }

    public function test_the_count_of_data_sources_is_never_worded_as_access(): void
    {
        $this->report('tasks', [$this->yesterday() => ['open' => 5]]);

        foreach (['/dashboard', '/workspace/figures', '/workspace/sources'] as $path) {
            $html = strip_tags($this->actingAs($this->owner)->get($path)->assertOk()->getContent());

            $this->assertDoesNotMatchRegularExpression('/platforms? (is |are )?(live|active)\b/i', $html, $path);
            $this->assertDoesNotMatchRegularExpression('/platforms connected(?! as data sources)/i', $html, $path);
        }
    }

    public function test_everything_the_old_overview_did_is_still_one_click_away(): void
    {
        $html = $this->actingAs($this->owner)->get('/dashboard')->assertOk()->getContent();

        foreach (['workspace.figures', 'workspace.insights', 'workspace.briefing', 'workspace.ask', 'workspace.reports', 'workspace.sources', 'me.home'] as $route) {
            $this->assertStringContainsString('href="'.route($route).'"', $html, $route);
        }

        $this->actingAs($this->owner)->get('/workspace/figures')->assertOk()
            ->assertSeeLivewire('analytics.metrics-panel')->assertSeeLivewire('analytics.dashboard-builder-panel')
            ->assertSeeLivewire('analytics.alerts-panel')->assertSeeLivewire('analytics.recommendations-panel');
        $this->actingAs($this->owner)->get('/workspace/sources')->assertOk()->assertSeeLivewire('analytics.ecosystem-map-panel');
    }

    public function test_the_landing_is_one_page_without_script_and_carries_no_inline_handlers(): void
    {
        $html = $this->actingAs($this->owner)->get('/dashboard')->assertOk()->getContent();
        $this->assertSame(1, preg_match('/<div class="ld" .*<\/div>\s*<\/main>/s', $html, $m));

        $this->assertDoesNotMatchRegularExpression('/\son[a-z]+="/', $m[0]);
        $this->assertStringNotContainsString('style="', $m[0]);
        $this->assertSame(1, substr_count($html, '<h1'));
    }

    public function test_the_panel_is_off_by_default_and_the_landing_does_not_call_infodot(): void
    {
        $this->assertFalse((require base_path('config/analytics.php'))['business_platforms']['enabled'], 'ANALYTICS_BUSINESS_PLATFORMS defaults to false');
        $this->assertFalse(config('analytics.business_platforms.enabled'));

        Http::fake();

        $this->actingAs($this->owner)->get('/dashboard')->assertOk()
            ->assertDontSeeLivewire(BusinessPlatformsPanel::class)->assertDontSee('Your business platforms');
        Http::assertNothingSent();
    }

    public function test_switched_on_the_landing_carries_the_panel_and_still_loads_without_waiting_for_infodot(): void
    {
        Http::fake();
        config(['analytics.business_platforms.enabled' => true, 'ecosystem.platforms.infodot.url' => 'https://infodot.test']);

        // Lazy: the page renders the placeholder (with a plain link for anyone without script) and InfoDot is asked afterwards.
        $this->actingAs($this->owner)->get('/dashboard')->assertOk()
            ->assertSeeLivewire(BusinessPlatformsPanel::class)
            ->assertSee('Your business platforms')
            ->assertSeeHtml('href="https://infodot.test/platforms"');
        Http::assertNothingSent();
    }
}
