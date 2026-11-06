<?php

namespace Tests\Feature\Intelligence;

use App\Models\Intelligence\Briefing;
use App\Models\Intelligence\Geography;
use App\Models\Intelligence\Indicator;
use App\Models\Intelligence\Item;
use App\Models\Intelligence\Observation;
use App\Models\Intelligence\PlatformCheck;
use App\Models\Intelligence\SearchLog;
use App\Models\Intelligence\Source;
use App\Models\Team;
use App\Models\User;
use App\Services\Intelligence\BriefingBuilder;
use App\Services\Intelligence\Classifier;
use App\Services\Intelligence\ReferenceData;
use App\Support\PageCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/** What a visitor who never signs in can and cannot see, and whether what they see is honest. */
class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(ReferenceData::class)->install();
    }

    /** Readings are placed so the newest sits $monthsAgo months back. */
    private function series(string $key, array $values, string $place = 'ZAF', int $monthsAgo = 3, string $step = 'Year'): Indicator
    {
        $indicator = Indicator::where('key', $key)->firstOrFail();
        $geo = Geography::where('code', $place)->value('id');
        $method = 'sub'.$step.'s';

        foreach ($values as $i => $value) {
            $period = now()->subMonths($monthsAgo)->startOfMonth()->{$method}(count($values) - 1 - $i);
            Observation::create(['indicator_id' => $indicator->id, 'geography_id' => $geo, 'period' => $period, 'value' => $value, 'first_seen_at' => now(), 'last_seen_at' => now()]);
        }

        return $indicator;
    }

    private function item(string $title, string $source = 'federal-reserve', ?string $summary = 'The board said something.', array $extra = []): Item
    {
        return Item::create($extra + ['uuid' => (string) Str::uuid(), 'source_id' => Source::where('key', $source)->value('id'), 'kind' => 'announcement', 'title' => $title, 'source_summary' => $summary, 'url' => 'https://www.federalreserve.gov/x/'.Str::random(8), 'url_hash' => hash('sha256', Str::random(20)), 'title_hash' => hash('sha256', $title), 'published_at' => now()->subDay(), 'collected_at' => now()]);
    }

    public function test_every_public_page_renders_on_an_empty_library_without_inventing_anything(): void
    {
        foreach (['/', '/industries', '/industries/manufacturing', '/countries', '/countries/south-africa', '/indicators', '/newsroom', '/briefings', '/sources', '/find', '/find?q=inflation', '/method', '/ecosystem'] as $path) {
            $this->get($path)->assertOk();
        }

        $this->get('/')->assertSee('No figures yet')->assertSee('Not available');
        $this->get('/briefings')->assertSee('Not published yet');
        $this->get('/briefings/global-business')->assertNotFound();
        $this->get('/indicators/wb.gdp-growth')->assertNotFound();
        $this->get('/nothing-here')->assertNotFound()->assertSee('not in the library');
    }

    public function test_a_visitor_gets_pages_with_no_cookie_a_strict_policy_and_a_shared_cache(): void
    {
        config(['intelligence.page_cache' => true]);
        $response = $this->get('/');

        $response->assertOk()->assertHeaderMissing('Set-Cookie')->assertHeader('X-Frame-Options', 'DENY');
        $this->assertStringContainsString("script-src 'self'", $response->headers->get('Content-Security-Policy'));
        $this->assertStringNotContainsString("'unsafe-inline'", $response->headers->get('Content-Security-Policy'));
        $this->assertStringNotContainsString('<style', $response->getContent());
        $this->assertDoesNotMatchRegularExpression('/\sstyle="/', $response->getContent());
        $this->assertStringContainsString('public', $response->headers->get('Cache-Control'));
        $this->assertSame('hit', $this->get('/')->headers->get('X-Library-Cache'));
    }

    public function test_search_is_never_cached_and_made_up_query_strings_cannot_fill_the_cache(): void
    {
        config(['intelligence.page_cache' => true]);
        $this->get('/find?q=gold');
        $this->assertNull($this->get('/find?q=gold')->headers->get('X-Library-Cache'));

        $this->get('/newsroom?industry=energy');
        $this->assertSame('hit', $this->get('/newsroom?industry=energy&tracking=abc123')->headers->get('X-Library-Cache'), 'an unread parameter must not make a new cache entry');
        $this->assertNull($this->get('/newsroom?industry='.str_repeat('x', 200))->headers->get('X-Library-Cache'));
    }

    public function test_no_public_route_starts_a_session_or_shows_anything_private(): void
    {
        $user = User::factory()->create();
        $team = Team::forceCreate(['user_id' => $user->id, 'name' => 'Secretco Holdings', 'personal_team' => false]);

        $public = collect(Route::getRoutes()->getRoutes())->filter(fn ($r) => str_starts_with((string) $r->getName(), 'intel.'));
        $this->assertGreaterThan(10, $public->count());

        foreach ($public as $route) {
            $middleware = implode(',', $route->gatherMiddleware());
            $this->assertStringNotContainsString('StartSession', $middleware, $route->getName());
            $this->assertStringNotContainsString('auth', $middleware, $route->getName());
        }

        foreach (['/', '/sources', '/newsroom', '/countries', '/industries'] as $path) {
            $this->get($path)->assertDontSee('Secretco');
        }

        $this->assertNotNull($team);
    }

    public function test_a_figure_carries_its_source_period_licence_and_every_value(): void
    {
        $this->series('wb.gdp-growth', [1.0, 1.4, 1.9], 'ZAF', 3);

        $page = $this->get('/indicators/wb.gdp-growth?place=ZAF')->assertOk();
        $page->assertSee('1.9%', false)->assertSee('World Bank Indicators')->assertSee('Show working')->assertSee('Source fact')->assertSee('Current')
            ->assertSee('Every value we hold')->assertSee('CC BY 4.0', false);
        $page->assertSee('Up 0.5 points', false);

        $this->get('/indicators/wb.gdp-growth/values.csv?place=ZAF')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertSee('# Source: World Bank')->assertSee('period,value');
    }

    public function test_an_old_reading_is_called_out_of_date_with_its_date_and_never_current(): void
    {
        $this->series('wb.inflation', [4.0, 4.2], 'ZAF', 60);

        $page = $this->get('/indicators/wb.inflation?place=ZAF')->assertOk();
        $page->assertSee('Out of date');
        $page->assertDontSee('status current');

        $this->get('/industries/retail-and-commerce')->assertOk()->assertSee('Out of date');
    }

    public function test_a_series_we_may_not_publish_is_not_reachable_listed_found_or_mapped(): void
    {
        $indicator = $this->series('wb.gdp-growth', [1.0, 2.0]);
        $indicator->update(['is_published' => false]);

        $this->get('/indicators/wb.gdp-growth')->assertNotFound();
        $this->get('/indicators/wb.gdp-growth/values.csv')->assertNotFound();
        $this->get('/indicators')->assertDontSee('GDP growth');
        $this->get('/find?q=GDP')->assertDontSee('/indicators/wb.gdp-growth');
        $this->assertStringNotContainsString('wb.gdp-growth', $this->get('/sitemap.xml')->getContent());
    }

    public function test_a_development_links_to_the_original_and_adds_nothing_it_may_not(): void
    {
        $item = $this->item('Board announces enforcement action');
        $page = $this->get('/newsroom/'.$item->uuid)->assertOk();
        $page->assertSee('Read the original at Federal Reserve')->assertSee($item->url, false)->assertSee('The board said something.');
        $page->assertDontSee('AI summary');

        Source::where('key', 'federal-reserve')->update(['allows_summary' => false]);
        $item->update(['ai_summary' => 'Invented words.', 'ai_model' => 'm', 'ai_generated_at' => now()]);
        $this->get('/newsroom/'.$item->uuid)->assertDontSee('Invented words');

        $item->update(['hidden_at' => now()]);
        $this->get('/newsroom/'.$item->uuid)->assertNotFound();
        $this->get('/newsroom')->assertDontSee('Board announces enforcement action');
    }

    public function test_taking_an_item_down_retires_every_cached_page_at_once(): void
    {
        config(['intelligence.page_cache' => true]);
        $item = $this->item('Item to be taken down');

        $this->get('/newsroom/'.$item->uuid)->assertOk();
        $item->update(['hidden_at' => now()]);
        $this->get('/newsroom/'.$item->uuid)->assertOk();

        PageCache::flush();
        $this->get('/newsroom/'.$item->uuid)->assertNotFound();
    }

    public function test_the_newsroom_filters_by_industry_and_says_so_when_nothing_matches(): void
    {
        $this->item('Gold mining output rises');
        $this->item('A speech on the weather');
        app(Classifier::class)->classifyPending();

        $this->get('/newsroom?industry=mining-and-minerals')->assertSee('Gold mining output rises')->assertDontSee('A speech on the weather');
        $this->get('/newsroom?industry=healthcare')->assertSee('Nothing to show');
        $this->get('/newsroom?industry=not-an-industry')->assertOk();
        $this->get('/newsroom?kind=bogus')->assertOk();
        $this->get('/newsroom?industry[]=x&place[a]=b')->assertOk();
    }

    public function test_a_question_is_answered_only_from_stored_figures_and_cites_them(): void
    {
        $this->series('wb.inflation', [20.0, 23.0], 'NGA', 3);

        $page = $this->get('/find?q='.urlencode('inflation in Nigeria'))->assertOk();
        $page->assertSee('Inflation, consumer prices in Nigeria')->assertSee('23.0%', false)->assertSee('World Bank Indicators')
            ->assertSee('No model wrote this answer')->assertSee('/indicators/wb.inflation?place=NGA', false);
    }

    public function test_a_question_we_cannot_answer_says_so_and_names_no_figure(): void
    {
        $page = $this->get('/find?q='.urlencode('How is South African manufacturing doing?'))->assertOk();
        $page->assertSee('We do not have figures that answer that')->assertSee('We hold no stored figures that answer this question');
        $page->assertDontSee('No model wrote this answer');

        $this->get('/find?q=zzzzzz')->assertSee('We found nothing for');
    }

    public function test_search_remembers_what_was_asked_and_whether_it_found_anything_but_not_who(): void
    {
        $this->get('/find?q=Zzzz+Nothing');

        $log = SearchLog::firstOrFail();
        $this->assertSame('zzzz nothing', $log->query);
        $this->assertSame(0, $log->hits);
        $this->assertSame(['id', 'query', 'hits', 'day', 'searched_at'], array_keys($log->getAttributes()));
    }

    public function test_search_does_not_mistake_a_fragment_inside_a_word_for_a_match(): void
    {
        $this->item('Corporate report published', 'federal-reserve', 'Accounts for the year.');
        $this->item('Discount rate minutes', 'federal-reserve', 'The rate was held.');

        $page = $this->get('/find?q=rate')->assertOk();
        $page->assertSee('Discount rate minutes')->assertDontSee('Corporate report published');
    }

    public function test_search_is_rate_limited_and_oversized_questions_are_refused(): void
    {
        $this->get('/find?q='.str_repeat('a', 400))->assertOk();
        $this->assertSame(160, mb_strlen(SearchLog::firstOrFail()->query));
        $this->get('/find?q[]=x')->assertOk();

        for ($i = 0; $i < 28; $i++) {
            $this->get('/find?q=gold'.$i)->assertOk();
        }

        $this->get('/find?q=gold')->assertStatus(429);
    }

    public function test_a_briefing_is_built_by_code_from_stored_figures_and_every_line_links_back(): void
    {
        $this->series('wb.gdp-growth', [1.0, 1.9], 'ZAF', 3);
        $this->series('wb.inflation', [4.0, 5.0], 'ZAF', 3);
        $this->item('Treasury announces a measure');

        $this->assertGreaterThan(0, app(BriefingBuilder::class)->refreshToday());

        $briefing = Briefing::where('edition', 'economic-developments')->firstOrFail();
        $this->get('/briefings/economic-developments')->assertRedirect('/briefings/economic-developments/'.$briefing->edition_date->toDateString());
        $page = $this->get('/briefings/economic-developments/'.$briefing->edition_date->toDateString())->assertOk();
        $page->assertSee('No person or model wrote these lines')->assertSee('GDP growth, South Africa')->assertSee('/indicators/wb.gdp-growth', false);
        $this->get('/briefings/economic-developments/not-a-date')->assertNotFound();
        $this->get('/briefings/invented-edition/2026-01-01')->assertNotFound();

        $this->assertSame(1, app(BriefingBuilder::class)->refreshToday() > 0 ? Briefing::where('edition', 'economic-developments')->count() : 0, 'refreshing the same day edits it, it does not add another');
    }

    public function test_the_sources_page_shows_failures_and_what_is_waiting_for_permission(): void
    {
        Source::where('key', 'world-bank')->update(['last_success_at' => now()->subDays(40), 'consecutive_failures' => 3, 'last_error' => 'The source could not be reached.']);

        $page = $this->get('/sources')->assertOk();
        $page->assertSee('World Bank Indicators')->assertSee('3 failed attempts since')->assertSee('Waiting for permission')->assertSee('South African Reserve Bank');
        $page->assertSee('Out of date');
    }

    public function test_the_infodot_directory_is_offered_only_when_infodot_answered_a_check(): void
    {
        $this->get('/industries/manufacturing')->assertDontSee('Browse the directory');

        PlatformCheck::create(['key' => 'infodot', 'url' => 'https://infodot.co.za', 'status' => 200, 'checked_at' => now(), 'last_ok_at' => now()]);
        $this->get('/industries/manufacturing')->assertSee('Browse the directory')->assertSee('/go/directory/manufacturing', false);

        PlatformCheck::where('key', 'infodot')->update(['last_ok_at' => now()->subDays(10), 'status' => 503]);
        $this->get('/industries/manufacturing')->assertDontSee('Browse the directory');
    }

    public function test_the_sitemap_lists_pages_that_exist_and_nothing_else(): void
    {
        $this->series('wb.gdp-growth', [1.0, 2.0]);
        $item = $this->item('Listed item');

        $xml = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml')->getContent();
        $this->assertStringContainsString('/indicators/wb.gdp-growth', $xml);
        $this->assertStringContainsString($item->uuid, $xml);
        $this->assertStringNotContainsString('/indicators/wb.inflation', $xml, 'a series with no readings has no page');
    }
}
