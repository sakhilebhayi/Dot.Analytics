<?php

namespace Tests\Feature\Intelligence;

use App\Models\Intelligence\PlatformCheck;
use App\Models\Intelligence\SearchLog;
use App\Models\User;
use App\Services\Intelligence\ReferenceData;
use App\Services\Intelligence\SiteMeasures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** How the library is used, counted without cookies and without keeping anyone. */
class SiteMeasuresTest extends TestCase
{
    use RefreshDatabase;

    private const HUMAN = 'Mozilla/5.0 (Macintosh) AppleWebKit/537.36 Chrome/120 Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();
        app(ReferenceData::class)->install();
        config(['intelligence.measure' => true, 'ecosystem.sso_only' => false]);
    }

    private function visit(string $path = '/', string $agent = self::HUMAN, string $ip = '203.0.113.5'): void
    {
        $this->withHeaders(['User-Agent' => $agent])->withServerVariables(['REMOTE_ADDR' => $ip])->get($path);
    }

    private function db()
    {
        return DB::connection(config('intelligence.connection'));
    }

    public function test_a_human_page_view_is_counted_by_area_without_a_cookie(): void
    {
        $this->visit('/industries/manufacturing');
        $this->visit('/industries/manufacturing');
        $this->visit('/');

        $this->assertSame(2, (int) $this->db()->table('visits')->where(['area' => 'industries', 'target' => 'manufacturing'])->value('views'));
        $this->assertSame(1, (int) $this->db()->table('visits')->where(['area' => 'home'])->value('views'));
        $this->assertSame(1, (int) $this->db()->table('visits')->where(['area' => '_all'])->value('visitors'), 'one person, however many pages');
    }

    public function test_bots_errors_downloads_feeds_and_prefetches_are_not_counted(): void
    {
        $this->visit('/', 'Googlebot/2.1');
        $this->visit('/', 'curl/8.0');
        $this->visit('/', '');
        $this->visit('/nope-nothing');
        $this->visit('/sitemap.xml');
        $this->withHeaders(['User-Agent' => self::HUMAN, 'Sec-Purpose' => 'prefetch'])->get('/');

        $this->assertSame(0, $this->db()->table('visits')->count());
    }

    public function test_the_same_person_returning_in_the_week_is_counted_as_returning_once(): void
    {
        $this->travelTo(now()->startOfWeek()->addHours(9));
        $this->visit();
        $this->visit('/industries');
        $this->assertSame(0, (int) $this->db()->table('visits')->where('area', '_all')->sum('returning'));

        $this->travelTo(now()->addDay());
        $this->visit();
        $this->visit('/method');

        $today = $this->db()->table('visits')->where(['area' => '_all', 'day' => now()->toDateString()])->first();
        $this->assertSame([1, 1], [(int) $today->visitors, (int) $today->returning]);

        $this->visit('/', self::HUMAN, '198.51.100.9');
        $this->assertSame(2, (int) $this->db()->table('visits')->where(['area' => '_all', 'day' => now()->toDateString()])->value('visitors'));
    }

    public function test_the_weekly_salt_means_nobody_can_be_followed_past_the_week(): void
    {
        $this->travelTo(now()->startOfWeek()->addHours(9));
        $this->visit();
        $first = $this->db()->table('visitor_days')->value('hash');

        $this->travelTo(now()->addWeek());
        $this->visit();

        $hashes = $this->db()->table('visitor_days')->pluck('hash')->all();
        $this->assertCount(2, array_unique($hashes), 'a new week is a new hash for the same person');
        $this->assertSame(0, (int) $this->db()->table('visits')->where('day', now()->toDateString())->where('area', '_all')->value('returning'));
        $this->assertNotNull($first);
        $this->assertContains($first, $hashes);
    }

    public function test_nothing_that_identifies_a_person_is_stored(): void
    {
        $this->visit('/', self::HUMAN, '203.0.113.77');

        $stored = json_encode([$this->db()->table('visitor_days')->get(), $this->db()->table('visits')->get()]);
        $this->assertStringNotContainsString('203.0.113.77', $stored);
        $this->assertStringNotContainsString('Mozilla', $stored);
        $this->assertSame(['hash', 'day', 'returning'], array_keys((array) $this->db()->table('visitor_days')->first()));
        $this->assertNotSame(sha1('203.0.113.77'), $this->db()->table('visitor_days')->value('hash'));
    }

    public function test_counting_can_never_break_a_page(): void
    {
        $this->db()->statement('drop table visits');

        $this->withHeaders(['User-Agent' => self::HUMAN])->get('/')->assertOk();
    }

    public function test_a_link_to_another_platform_is_built_by_us_counted_and_cannot_be_turned_into_a_redirect(): void
    {
        $this->get('/go/directory/manufacturing')->assertNotFound();

        PlatformCheck::create(['key' => 'infodot', 'url' => 'https://infodot.co.za', 'status' => 200, 'checked_at' => now(), 'last_ok_at' => now()]);

        $this->get('/go/directory/manufacturing')->assertRedirect('https://infodot.co.za/businesses?category=manufacturing');
        $this->get('/go/directory-verified/manufacturing')->assertRedirect('https://infodot.co.za/businesses?category=manufacturing&verified=1');
        $this->get('/go/infodot/directory')->assertRedirect('https://infodot.co.za/businesses');
        $this->get('/go/platform/infodot')->assertRedirect('https://infodot.co.za');
        $this->assertSame(1, (int) $this->db()->table('referrals')->where(['kind' => 'directory', 'target' => 'manufacturing'])->value('clicks'));

        foreach (['/go/platform/evil', '/go/directory/not-an-industry', '/go/anything/at-all', '/go/platform/https:%2F%2Fevil.test'] as $path) {
            $this->get($path)->assertNotFound();
        }
    }

    public function test_pruning_removes_week_old_hashes_and_old_questions_and_keeps_the_totals(): void
    {
        $this->db()->table('visitor_days')->insert([['hash' => str_repeat('a', 40), 'day' => now()->subDays(9)->toDateString(), 'returning' => 0], ['hash' => str_repeat('b', 40), 'day' => now()->subDays(2)->toDateString(), 'returning' => 0]]);
        $this->db()->table('visits')->insert(['day' => now()->subDays(200)->toDateString(), 'area' => 'home', 'target' => '', 'views' => 5, 'visitors' => 0, 'returning' => 0]);
        SearchLog::create(['query' => 'old', 'hits' => 0, 'day' => now()->subDays(100)->toDateString(), 'searched_at' => now()->subDays(100)]);
        SearchLog::create(['query' => 'new', 'hits' => 1, 'day' => now()->toDateString(), 'searched_at' => now()]);

        $this->artisan('intelligence:prune')->assertSuccessful();

        $this->assertSame(1, $this->db()->table('visitor_days')->count());
        $this->assertSame(['new'], SearchLog::pluck('query')->all());
        $this->assertSame(1, $this->db()->table('visits')->count(), 'daily totals carry no one and are kept');
    }

    public function test_the_operations_page_is_for_operators_only_and_shows_failed_searches(): void
    {
        $this->get('/find?q=zzzz+nothing');
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->get('/me/operate')->assertForbidden();

        $user->forceFill(['is_platform_operator' => true])->save();
        $this->actingAs($user)->get('/me/operate')->assertOk()->assertSee('zzzz nothing')->assertSee('Sources')->assertSee('Latest collection runs');
    }

    public function test_the_measures_service_reports_what_it_is_for(): void
    {
        $this->assertTrue(app(SiteMeasures::class)->connection()->getSchemaBuilder()->hasTable('visitor_days'));
    }
}
