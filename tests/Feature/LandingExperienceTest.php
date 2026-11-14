<?php

namespace Tests\Feature;

use App\Livewire\Analytics\BusinessPlatformsPanel;
use App\Models\AnalyticsAlert;
use App\Models\DataSource;
use App\Models\Recommendation;
use App\Models\User;
use App\Services\AiModelRouter;
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
        $this->assertSame(1, preg_match('/<a class="ld-primary" data-primary-action href="([^"]+)">([^<]+)<\/a>/', $html, $m));

        return [trim($m[2]), $m[1]];
    }

    private function tape(string $html): string
    {
        $this->assertSame(1, preg_match('/<section class="ld-tape".*?<\/section>/s', $html, $m));

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

    public function test_the_welcome_uses_the_persons_first_name_and_the_companys_name(): void
    {
        $this->actingAs($this->owner)->get('/dashboard')->assertOk()
            ->assertSee('Welcome, Thandi. You are reading for Karoo Freight.')
            ->assertDontSee('Mokoena. You are reading')
            ->assertSee('Every figure shows its working.');
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
        $tape = preg_replace('/\s+/', ' ', strip_tags($this->tape($response->getContent())));
        $day = now()->utc()->startOfDay()->subDay()->format('j M Y');

        // One line from each reporting platform first, each with the platform, the day and the arrival.
        $this->assertStringContainsString('Open tasks 122 Up 1 on the report before Sent by Dot.Tasks, for '.$day.', received', $tape);
        $this->assertStringContainsString('Headcount 64 First report of this figure Sent by Dot.HR, for '.$day, $tape);
        $this->assertStringContainsString('Overdue tasks 12 Down 2 on the report before', $tape);
        $this->assertStringContainsString('2 of 11 platforms that can report are reporting data: Dot.Tasks and Dot.HR. Dot.Emall is connected and has not sent a figure yet. Counted from this company\'s data sources.', $tape);

        // Nothing else in the tape is a number: take away what was seeded and the dates, and no digit is left.
        $rest = str_replace([$day, '2 of 11', 'Up 1', 'Down 2', '122', '64', '12'], '', $tape);
        $this->assertDoesNotMatchRegularExpression('/\d(?! (second|minute|hour|day)s? ago)/', preg_replace('/\d+ (second|minute|hour|day)s? ago/', '', $rest));

        $response->assertSee('2 alerts open')->assertSee('Overdue tasks are above their usual range')
            ->assertSee('1 recommended action')->assertSee('Review the oldest leave requests')
            ->assertDontSee('Nothing is waiting.');
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
