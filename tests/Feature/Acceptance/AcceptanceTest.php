<?php

namespace Tests\Feature\Acceptance;

use App\Models\Intelligence\Geography;
use App\Models\Intelligence\Indicator;
use App\Models\Intelligence\Item;
use App\Models\Intelligence\Observation;
use App\Models\Intelligence\PlatformCheck;
use App\Models\Intelligence\Source;
use App\Models\Team;
use App\Models\User;
use App\Services\Intelligence\Classifier;
use App\Services\Intelligence\ReferenceData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\Support\BuildsTeams;
use Tests\TestCase;

/**
 * Brief section 19, item by item, against the running application rather than its parts. The number in each test name is the
 * item's number in the brief. Items that are about a real browser (22, 23) are proved by the crawl recorded in
 * docs/programme/R38_READINESS.md and are only guarded here against regression. docs/programme/R38_ACCEPTANCE.md maps every
 * item to its evidence.
 */
class AcceptanceTest extends TestCase
{
    use BuildsTeams, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(ReferenceData::class)->install();
        config(['ecosystem.sso_only' => false]);
    }

    private function reading(string $key, string $place, array $values, int $monthsAgo = 3): Indicator
    {
        $i = Indicator::where('key', $key)->firstOrFail();
        $geo = Geography::where('code', $place)->value('id');

        foreach ($values as $n => $v) {
            Observation::create(['indicator_id' => $i->id, 'geography_id' => $geo, 'period' => now()->subMonths($monthsAgo)->startOfMonth()->subYears(count($values) - 1 - $n), 'value' => $v, 'first_seen_at' => now(), 'last_seen_at' => now()]);
        }

        return $i;
    }

    private function item(string $title, string $summary = 'Summary.', ?string $source = 'federal-reserve'): Item
    {
        return Item::create(['uuid' => (string) Str::uuid(), 'source_id' => Source::where('key', $source)->value('id'), 'kind' => 'announcement', 'title' => $title, 'source_summary' => $summary, 'url' => 'https://www.federalreserve.gov/x/'.Str::random(8), 'url_hash' => hash('sha256', Str::random(20)), 'title_hash' => hash('sha256', $title), 'published_at' => now()->subDay(), 'collected_at' => now()]);
    }

    public function test_01_a_new_visitor_reaches_the_public_experience_with_no_account_cookie_or_script_dependency(): void
    {
        $this->get('/')->assertOk()->assertHeaderMissing('Set-Cookie')->assertSee('Business figures that show their working');
    }

    public function test_02_a_visitor_finds_real_intelligence_without_registering(): void
    {
        $this->reading('wb.inflation', 'ZAF', [5.1, 4.4, 3.2]);
        $this->item('Governor announces a rate decision');

        $this->get('/')->assertOk()->assertSee('3.2%', false)->assertSee('Governor announces a rate decision');
        $this->get('/briefings')->assertOk();
    }

    public function test_03_an_industry_page_shows_that_industrys_series_and_not_another(): void
    {
        $this->reading('wb.manufacturing-share', 'ZAF', [13.0, 12.3]);
        $this->reading('wb.mineral-rents', 'ZAF', [3.1, 2.9]);

        $this->get('/industries/manufacturing')->assertOk()->assertSee('Manufacturing, value added')->assertDontSee('Mineral rents');
        $this->get('/industries/mining-and-minerals')->assertOk()->assertSee('Mineral rents')->assertDontSee('Manufacturing, value added');
    }

    public function test_04_a_geographic_filter_returns_that_places_figures_and_never_another(): void
    {
        $this->reading('wb.inflation', 'ZAF', [3.0, 3.2]);
        $this->reading('wb.inflation', 'NGA', [20.0, 23.0]);

        $this->get('/indicators/wb.inflation?place=NGA')->assertOk()->assertSee('23.0%', false)->assertSee('Nigeria');
        $this->get('/indicators/wb.inflation?place=ZAF')->assertOk()->assertSee('3.2%', false);
        $this->get('/countries/nigeria')->assertOk()->assertSee('23.0%', false)->assertDontSee('3.2%');

        $this->item('Nigeria central bank holds', 'The bank of Nigeria held.');
        $this->item('Gold output in South Africa', 'Mining output.');
        app(Classifier::class)->classifyPending();
        $this->get('/newsroom?place=NGA')->assertSee('Nigeria central bank holds')->assertDontSee('Gold output in South Africa');
    }

    public function test_05_every_development_links_to_its_original_source(): void
    {
        $item = $this->item('Board publishes minutes');

        $this->get('/newsroom')->assertSee($item->title);
        $this->get('/newsroom/'.$item->uuid)->assertSee('href="'.$item->url.'"', false)->assertSee('Read the original at Federal Reserve');
    }

    public function test_06_freshness_is_visible_on_every_figure(): void
    {
        $this->reading('wb.gdp-growth', 'ZAF', [1.0, 1.1], 3);
        $this->reading('wb.unemployment', 'ZAF', [30.0, 32.0], 70);

        $home = $this->get('/indicators/wb.gdp-growth?place=ZAF')->assertSee('Current')->assertSee('checked');
        $this->get('/indicators/wb.unemployment?place=ZAF')->assertSee('Out of date');
        $this->get('/countries')->assertSee('Not available');
        $this->assertNotNull($home);
    }

    public function test_07_search_returns_what_was_asked_for_first(): void
    {
        $this->item('Interest rate decision explained', 'A short summary.');
        $this->item('Quarterly report', 'Mentions the interest rate in passing.');

        $html = $this->get('/find?q='.urlencode('interest rate'))->getContent();

        $this->assertLessThan(strpos($html, 'Quarterly report'), strpos($html, 'Interest rate decision explained'), 'a headline match ranks above a mention in the summary');
        $this->get('/find?q=zzzxqj')->assertSee('We found nothing');
    }

    public function test_08_a_visitor_can_move_from_a_figure_to_related_information(): void
    {
        $this->reading('wb.gdp-growth', 'ZAF', [1.0, 1.1]);
        $this->reading('wb.gdp-growth', 'KEN', [4.0, 4.6]);
        $first = $this->item('Kenya growth outlook', 'Mentions Kenya.');
        $this->item('Another growth item', 'Kenya too.');
        app(Classifier::class)->classifyPending();

        $this->get('/indicators/wb.gdp-growth?place=ZAF')->assertSee('Against other places')->assertSee('Kenya')->assertSee('/indicators/wb.gdp-growth?place=KEN', false);
        $this->get('/newsroom/'.$first->uuid)->assertOk()->assertSee('Kenya');
        $this->get('/industries/manufacturing')->assertSee('place=KEN', false);
    }

    public function test_09_a_chart_shows_exactly_the_stored_values(): void
    {
        $i = $this->reading('wb.gdp-growth', 'ZAF', [1.2, 2.4, -0.6, 1.9]);

        $html = $this->get('/indicators/wb.gdp-growth?place=ZAF')->assertOk()->getContent();
        preg_match_all('/data-readout="([^"]+)"/', html_entity_decode($html), $readouts);
        $stored = Observation::where('indicator_id', $i->id)->orderBy('period')->pluck('value')->map(fn ($v) => number_format($v, 1, '.', "\u{00A0}"))->map(fn ($v) => str_replace('-', '−', $v))->all();

        $this->assertCount(4, $readouts[1]);
        foreach ($stored as $n => $value) {
            $this->assertStringContainsString($value, $readouts[1][$n]);
        }

        $this->assertStringContainsString('Every value we hold', $html, 'the same numbers are in a table for anyone who cannot use the chart');
    }

    public function test_10_report_details_are_available_without_an_account(): void
    {
        $this->reading('wb.gdp-growth', 'ZAF', [1.0, 1.1]);

        $this->get('/indicators/wb.gdp-growth?place=ZAF')->assertSee('Licence')->assertSee('Measures')->assertSee('Download the values (CSV)');
        $this->get('/indicators/wb.gdp-growth/values.csv?place=ZAF')->assertOk()->assertSee('# Source: World Bank');
        $this->get('/method')->assertSee('Five labels');
    }

    public function test_11_people_sign_in_through_infodot_and_cannot_around_it(): void
    {
        config(['ecosystem.sso_only' => true, 'ecosystem.infodot_url' => 'https://infodot.co.za']);

        $this->get('/login')->assertRedirect();
        $this->assertStringContainsString('infodot.co.za', (string) $this->get('/login')->headers->get('Location'));
        $user = User::factory()->create(['password' => bcrypt('a-password-123')]);
        $this->post('/login', ['email' => $user->email, 'password' => 'a-password-123'])->assertSessionHasErrors();
        $this->assertGuest();
    }

    public function test_12_and_13_a_person_can_personalise_and_is_alerted_only_about_what_they_watch(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->post('/me/watch', ['kind' => 'industry', 'key' => 'mining-and-minerals'])->assertRedirect('/me');
        $this->item('Gold mining output rises');
        $this->item('A speech about birds');
        app(Classifier::class)->classifyPending();
        $this->artisan('library:scan')->assertSuccessful();

        $this->actingAs($user)->get('/me/alerts')->assertSee('Gold mining output rises')->assertDontSee('A speech about birds');
    }

    public function test_14_private_business_analytics_stay_private(): void
    {
        $a = $this->ownerWithTeam();
        $b = $this->ownerWithTeam();
        $a->currentTeam->forceFill(['name' => 'Alpha Private Holdings'])->save();

        foreach (['/', '/sources', '/newsroom', '/countries', '/industries', '/find?q=Alpha', '/sitemap.xml', '/me'] as $path) {
            $this->get($path)->assertDontSee('Alpha Private Holdings');
        }

        $this->actingAs($b)->get('/dashboard')->assertOk()->assertDontSee('Alpha Private Holdings');
    }

    public function test_15_and_16_the_infodot_directory_is_only_reached_through_its_own_rules_and_only_when_it_answers(): void
    {
        $this->get('/industries/manufacturing')->assertDontSee('Browse the directory');
        $this->get('/go/directory-verified/manufacturing')->assertNotFound();

        PlatformCheck::create(['key' => 'infodot', 'url' => 'https://infodot.co.za', 'status' => 200, 'checked_at' => now(), 'last_ok_at' => now()]);

        $this->get('/industries/manufacturing')->assertSee('Verified only');
        $this->get('/go/directory-verified/manufacturing')->assertRedirect('https://infodot.co.za/businesses?category=manufacturing&verified=1');
        $this->assertStringNotContainsString('/business/', (string) $this->get('/go/directory/manufacturing')->headers->get('Location'), 'no business is named or exposed here, so none can be shown against its settings');
    }

    public function test_17_ecosystem_links_lead_only_to_platforms_that_answered_a_check(): void
    {
        $this->get('/ecosystem')->assertSee('No platform answered');

        PlatformCheck::create(['key' => 'files', 'url' => 'https://files.infodot.co.za', 'status' => 200, 'checked_at' => now(), 'last_ok_at' => now()]);
        PlatformCheck::create(['key' => 'hr', 'url' => 'https://hr.infodot.co.za', 'status' => 503, 'checked_at' => now(), 'last_ok_at' => now()->subDays(9)]);

        $this->get('/ecosystem')->assertSee('Dot.Files')->assertDontSee('Dot.HR');
        $this->get('/go/platform/hr')->assertNotFound();
    }

    public function test_21_pages_stay_cheap_at_a_realistic_volume(): void
    {
        $geos = Geography::where('kind', 'country')->pluck('id');
        $indicators = Indicator::where('is_published', true)->pluck('id');
        $rows = [];

        foreach ($indicators as $i) {
            foreach ($geos as $g) {
                foreach (range(0, 14) as $y) {
                    $rows[] = ['indicator_id' => $i, 'geography_id' => $g, 'period' => now()->subYears($y)->startOfYear()->toDateString(), 'value' => $y + $g, 'first_seen_at' => now(), 'last_seen_at' => now()];
                }

                if (count($rows) >= 2000) {
                    DB::connection(config('intelligence.connection'))->table('observations')->insert($rows);
                    $rows = [];
                }
            }
        }

        DB::connection(config('intelligence.connection'))->table('observations')->insert($rows);
        $sourceId = Source::where('key', 'federal-reserve')->value('id');

        foreach (range(1, 1500) as $n) {
            DB::connection(config('intelligence.connection'))->table('items')->insert(['uuid' => (string) Str::uuid(), 'source_id' => $sourceId, 'kind' => 'announcement', 'title' => "Development number {$n} about banks", 'source_summary' => 'Text', 'url' => 'https://www.federalreserve.gov/'.$n, 'url_hash' => hash('sha256', (string) $n), 'title_hash' => hash('sha256', "t{$n}"), 'published_at' => now()->subMinutes($n), 'collected_at' => now()]);
        }

        $this->assertGreaterThan(30000, Observation::count());

        foreach (['/' => 45, '/industries' => 12, '/industries/finance-and-banking' => 25, '/countries/south-africa' => 25, '/indicators/wb.gdp-growth?place=ZAF' => 15, '/newsroom' => 10, '/countries' => 12, '/sources' => 14, '/briefings' => 4] as $path => $budget) {
            DB::connection(config('intelligence.connection'))->enableQueryLog();
            DB::connection(config('intelligence.connection'))->flushQueryLog();
            $started = microtime(true);
            $this->get($path)->assertOk();
            $queries = count(DB::connection(config('intelligence.connection'))->getQueryLog());

            $this->assertLessThanOrEqual($budget, $queries, "{$path} ran {$queries} queries");
            $this->assertLessThan(3.0, microtime(true) - $started, "{$path} took too long at this volume");
        }
    }

    public function test_22_and_23_layouts_adapt_and_every_page_keeps_its_landmarks_labels_and_one_heading(): void
    {
        $this->reading('wb.gdp-growth', 'ZAF', [1.0, 1.1]);

        foreach (['/', '/industries', '/industries/manufacturing', '/countries', '/countries/south-africa', '/indicators', '/indicators/wb.gdp-growth?place=ZAF', '/newsroom', '/briefings', '/sources', '/find', '/method', '/plans', '/ecosystem'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();

            $this->assertStringContainsString('name="viewport"', $html, $path);
            $this->assertStringContainsString('class="skip"', $html, $path);
            $this->assertStringContainsString('<main id="main">', $html, $path);
            $this->assertSame(1, substr_count($html, '<h1'), "{$path} must have exactly one h1");
            $this->assertStringContainsString('<html lang="en">', $html, $path);
            $this->assertDoesNotMatchRegularExpression('/<input(?![^>]*type="hidden")(?![^>]*aria-label)(?![^>]*id=)[^>]*>/', $html, "{$path} has an unlabelled input");
            $this->assertDoesNotMatchRegularExpression('/<img(?![^>]*alt=)/', $html, "{$path} has an image with no alt");
        }

        $css = File::get(resource_path('css/public.css'));
        $this->assertStringContainsString('@media (max-width: 720px)', $css);
        $this->assertStringContainsString('@media (max-width: 1040px)', $css);
        $this->assertStringContainsString('prefers-reduced-motion', $css);
    }

    public function test_24_permissions_are_enforced_on_the_server(): void
    {
        $owner = $this->ownerWithTeam();
        $viewer = $this->memberOf($owner, 'viewer');

        // A guest first: acting as someone sticks for the rest of a test.
        $this->get('/me/operate')->assertRedirect();
        $this->assertContains($this->postJson('/api/v1/intelligence/run')->status(), [401, 403], 'a guest is refused');

        $this->actingAs($viewer)->get('/me/operate')->assertForbidden();
        $this->actingAs($viewer)->post('/me/watch', ['kind' => 'industry', 'key' => 'energy'])->assertRedirect('/me');
        $this->actingAs($viewer)->postJson('/api/v1/intelligence/run')->assertForbidden();
    }

    public function test_25_nothing_fabricated_remains_in_what_visitors_see(): void
    {
        $empty = $this->get('/')->assertSee('No figures yet')->getContent();
        $this->assertDoesNotMatchRegularExpression('/\d\s?%/', strip_tags($empty), 'an empty library shows no numbers');

        $forbidden = '/lorem ipsum|coming soon|todo|fake data|dummy|sample data|example\.com|123,456|\bxx+%/i';

        foreach (File::allFiles(resource_path('views/intel')) as $file) {
            $this->assertDoesNotMatchRegularExpression($forbidden, File::get($file->getPathname()), $file->getFilename());
        }

        foreach (File::allFiles(resource_path('views/components/intel')) as $file) {
            $this->assertDoesNotMatchRegularExpression($forbidden, File::get($file->getPathname()), $file->getFilename());
        }

        // Every source the library collects from states a licence and a credit line, and every series states what it measures.
        foreach (Source::where('is_enabled', true)->get() as $s) {
            $this->assertNotSame('', trim($s->licence), $s->key);
            $this->assertNotSame('', trim($s->attribution), $s->key);
        }

        foreach (Indicator::all() as $i) {
            $this->assertNotSame('', trim($i->definition), $i->key);
            $this->assertNotSame('', trim($i->unit), $i->key);
        }

        // Nothing is shown that was never collected.
        $this->assertSame(0, Observation::count());
        $this->assertSame(0, Item::count());
        $this->assertSame(0, Team::count());
    }
}
