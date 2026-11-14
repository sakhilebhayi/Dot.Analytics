<?php

namespace Tests\Feature;

use App\Models\AnalyticsAlert;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request as OutgoingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The headers carry the shared Dot UI (dot-ui): the search pill and the platform switcher. Presentation changed; the
 * search forms, routes, parameters and results did not. The switcher only shows what InfoDot says the CURRENT company
 * holds and leaves through InfoDot's /go (never straight to a host, never with a token). The public pages are shared by
 * everyone and know nobody, so they carry the pill and no switcher.
 */
class DotUiHeaderTest extends TestCase
{
    use RefreshDatabase;

    private const REDEEM = 'https://infodot.test/api/ecosystem/redeem';

    private const ACME = '11111111-1111-4111-8111-111111111111';

    private const GLOBEX = '22222222-2222-4222-8222-222222222222';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ecosystem.infodot' => ['redeem_url' => self::REDEEM, 'platform' => 'analytics', 'secret' => 'a-test-secret', 'timeout' => 2, 'provision' => true, 'entitlement_check' => true],
            'ecosystem.platforms.infodot.url' => 'https://infodot.test',
        ]);
    }

    private static function url(string $uuid): string
    {
        return 'https://infodot.test/api/ecosystem/companies/'.$uuid.'/platforms*';
    }

    private static function body(string $uuid, array $held = ['analytics', 'files', 'infodot']): array
    {
        $rows = [];
        foreach (['analytics' => 'Dot.Analytics', 'files' => 'Dot.Files', 'finance' => 'Dot.Finance', 'hr' => 'Dot.HR', 'infodot' => 'InfoDot', 'press' => 'Dot.Press'] as $key => $name) {
            $rows[] = ['key' => $key, 'name' => $name, 'status' => 'active', 'entitled' => in_array($key, $held, true), 'is_core' => false, 'access_mode' => 'everyone'];
        }

        return ['platform' => 'analytics', 'company' => ['uuid' => $uuid, 'personal' => false], 'platforms' => $rows];
    }

    private function fakeInfoDot(array $stubs): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake($stubs);
    }

    private function memberOf(string $uuid, string $name): User
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = Team::forceCreate(['user_id' => $user->id, 'name' => $name, 'personal_team' => false, 'infodot_team_uuid' => $uuid]);
        $user->switchTeam($team);

        return $user->fresh();
    }

    private function switcherHtml(string $html): string
    {
        $this->assertSame(1, preg_match('/<div class="dsw dsw--header".*?<p class="dsw-sr" data-dsw-live[^>]*><\/p>\s*<\/div>\s*<\/div>\s*<\/div>/s', $html, $m), 'the header carries the switcher');

        return $m[0];
    }

    // ── The search pills ─────────────────────────────────────────────────────

    public function test_the_public_header_search_keeps_its_form_route_and_parameter(): void
    {
        $html = $this->get(route('intel.find', ['q' => 'inflation']))->assertOk()->getContent();

        $this->assertSame(1, preg_match('/<form role="search" method="get"\s+action="([^"]+)"\s+aria-label="Search the library"/', $html, $form), 'a GET search form');
        $this->assertSame(route('intel.find'), html_entity_decode($form[1]));
        $this->assertMatchesRegularExpression('/<input[^>]*\bid="q-top"[^>]*\btype="search"[^>]*\bname="q"[^>]*\bplaceholder="Search or ask"[^>]*\bmaxlength="160"/', $html);
        $this->assertStringContainsString('class="dsr-input"', $html, 'the shared pill');
        $this->assertStringNotContainsString('<form class="find wide"', $html, 'the old header form is gone');
        $this->assertStringContainsString('id="q-menu"', $html, 'the menu search on a phone is the pill too');
        $this->assertStringContainsString('dsr dsr--nohint', $html, 'no invented Ctrl K hint');
    }

    public function test_the_page_search_boxes_in_the_body_are_not_touched(): void
    {
        $this->get('/')->assertOk()->assertSee('<form class="search-big"', false)->assertSee('id="q-home"', false);
    }

    public function test_a_public_page_carries_no_switcher_and_asks_infodot_nothing(): void
    {
        $this->fakeInfoDot(['*' => Http::response([], 500)]);

        $this->get('/')->assertOk()->assertDontSee('data-dsw', false)->assertDontSee('dot-switcher', false);
        $this->actingAs($this->memberOf(self::ACME, 'Acme Ltd'))->get('/'); // signed in does not change a page everyone shares
        Http::assertNothingSent();
    }

    public function test_the_workspace_header_search_keeps_its_form_route_and_parameter(): void
    {
        $this->fakeInfoDot([self::url(self::ACME) => Http::response(self::body(self::ACME))]);
        $html = $this->actingAs($this->memberOf(self::ACME, 'Acme Ltd'))->get(route('workspace.figures', ['q' => 'Gold']))->assertOk()->getContent();

        $this->assertSame(1, preg_match('/<form role="search" method="get"\s+action="([^"]+)"/', $html, $form));
        $this->assertSame(route('search'), html_entity_decode($form[1]));
        $this->assertMatchesRegularExpression('/<input[^>]*\bid="dsr-q"[^>]*\btype="search"[^>]*\bname="q"[^>]*\bvalue="Gold"[^>]*\bplaceholder="Search…"[^>]*\bmaxlength="100"/', $html);
        $this->assertStringNotContainsString('class="ws-search"><input', $html);
    }

    public function test_the_workspace_search_results_are_unchanged_and_never_cross_companies(): void
    {
        $this->fakeInfoDot([
            self::url(self::ACME) => Http::response(self::body(self::ACME)),
            self::url(self::GLOBEX) => Http::response(self::body(self::GLOBEX)),
        ]);
        $acme = $this->memberOf(self::ACME, 'Acme Ltd');
        $globex = $this->memberOf(self::GLOBEX, 'Globex Ltd');
        AnalyticsAlert::factory()->create(['team_id' => $acme->currentTeam->id, 'title' => 'Findable Acme']);
        AnalyticsAlert::factory()->create(['team_id' => $globex->currentTeam->id, 'title' => 'Findable Globex']);

        $page = $this->actingAs($acme)->get(route('search', ['q' => 'Findable']))->assertOk();
        $page->assertSee('Findable Acme')->assertDontSee('Findable Globex');
        // The in-page box is the compact filter, wired to the same Livewire property.
        $page->assertSee('class="dsr-filter"', false)->assertSee('wire:model.live.debounce.300ms="query"', false);

        $this->actingAs($globex)->get(route('search', ['q' => 'Findable']))->assertOk()->assertSee('Findable Globex')->assertDontSee('Findable Acme');
    }

    public function test_search_still_needs_a_signed_in_person(): void
    {
        $this->get(route('search', ['q' => 'x']))->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk()->assertDontSee('dsr-input', false)->assertDontSee('data-dsw', false);
    }

    // ── The switcher ────────────────────────────────────────────────────────

    public function test_a_member_sees_the_switcher_with_what_their_company_holds_and_the_current_platform_marked(): void
    {
        $this->fakeInfoDot([self::url(self::ACME) => Http::response(self::body(self::ACME))]);

        $html = $this->actingAs($this->memberOf(self::ACME, 'Acme Ltd'))->get(route('workspace.figures'))->assertOk()->getContent();
        $panel = $this->switcherHtml($html);

        $this->assertStringContainsString('data-state="ok"', $panel);
        $this->assertStringContainsString('data-platform="analytics"', $panel);
        $this->assertStringContainsString('Acme Ltd', $panel);
        $this->assertSame(1, preg_match('/<div class="dsw-bay is-current" aria-current="page">.*?You are here/s', $panel, $here));
        $this->assertStringContainsString('Dot.Analytics', $here[0]);
        $this->assertStringContainsString('href="https://infodot.test/go/files?company='.self::ACME.'"', $panel);
        $this->assertStringContainsString('href="https://infodot.test/home"', $panel);
        $this->assertStringNotContainsString('/go/hr', $panel, 'a platform the company does not hold is not a tile');
        $this->assertStringContainsString('href="https://infodot.test/platforms/hr"', $panel);

        $this->assertStringNotContainsString('token', strtolower($panel));
        $this->assertStringNotContainsString('a-test-secret', $html);
        preg_match_all('/href="([^"]+)"/', $panel, $hrefs);
        foreach ($hrefs[1] as $href) {
            $this->assertStringStartsWith('https://infodot.test/', $href, 'every way out of the switcher goes through InfoDot');
        }
    }

    public function test_the_signed_in_library_pages_carry_the_switcher_too(): void
    {
        $this->fakeInfoDot([self::url(self::ACME) => Http::response(self::body(self::ACME))]);

        $html = $this->actingAs($this->memberOf(self::ACME, 'Acme Ltd'))->get(route('me.home'))->assertOk()->getContent();

        $this->assertStringContainsString('href="https://infodot.test/go/files?company='.self::ACME.'"', $this->switcherHtml($html));
        $this->assertStringContainsString('/dot-ui/js/dot-switcher.js', $html);
    }

    public function test_the_switcher_asks_infodot_about_the_current_company_with_the_service_secret(): void
    {
        $this->fakeInfoDot([self::url(self::ACME) => Http::response(self::body(self::ACME))]);

        $this->actingAs($this->memberOf(self::ACME, 'Acme Ltd'))->get(route('workspace.figures'))->assertOk();

        Http::assertSent(fn (OutgoingRequest $r) => str_contains($r->url(), '/companies/'.self::ACME.'/platforms')
            && $r->hasHeader('Authorization', 'Bearer a-test-secret') && $r->hasHeader('X-Dot-Platform', 'analytics'));
        Http::assertNotSent(fn (OutgoingRequest $r) => ! str_contains($r->url(), self::ACME));
    }

    public function test_two_companies_never_see_each_others_switcher(): void
    {
        $this->fakeInfoDot([
            self::url(self::ACME) => Http::response(self::body(self::ACME, ['analytics', 'infodot', 'press'])),
            self::url(self::GLOBEX) => Http::response(self::body(self::GLOBEX, ['analytics', 'infodot', 'finance'])),
        ]);

        $acme = $this->switcherHtml($this->actingAs($this->memberOf(self::ACME, 'Acme Ltd'))->get(route('workspace.figures'))->getContent());
        $globex = $this->switcherHtml($this->actingAs($this->memberOf(self::GLOBEX, 'Globex Ltd'))->get(route('workspace.figures'))->getContent());

        $this->assertStringContainsString('/go/press?company='.self::ACME, $acme);
        $this->assertStringNotContainsString('/go/finance', $acme);
        $this->assertStringContainsString('/go/finance?company='.self::GLOBEX, $globex);
        $this->assertStringNotContainsString('/go/press', $globex);
        $this->assertStringNotContainsString(self::ACME, $globex);
    }

    public function test_a_failing_infodot_shows_the_degraded_switcher_and_never_blocks_the_page(): void
    {
        $member = $this->memberOf(self::ACME, 'Acme Ltd');

        foreach ([Http::response([], 503), fn () => throw new ConnectionException('timed out')] as $answer) {
            Cache::flush();
            $this->fakeInfoDot([self::url(self::ACME) => $answer]);

            $html = $this->actingAs($member)->get(route('workspace.figures'))->assertOk()->getContent();
            $panel = $this->switcherHtml($html);

            $this->assertStringContainsString('data-state="degraded"', $panel);
            $this->assertStringContainsString('Could not check your platforms just now', $panel);
            $this->assertStringNotContainsString('/go/press', $panel, 'degraded: nothing beyond the core set is implied, and InfoDot decides at /go');
            $this->assertStringNotContainsString('Not switched on', $panel, 'no claim about what the company lacks either');
            $this->assertStringContainsString('You are here', $panel);
        }
    }

    public function test_a_personal_workspace_asks_infodot_nothing_for_the_switcher(): void
    {
        $this->fakeInfoDot(['*' => Http::response([], 500)]);
        $user = User::factory()->withPersonalTeam()->create();

        $html = $this->actingAs($user)->get(route('workspace.figures'))->getContent();

        $this->assertStringContainsString('data-state="personal"', $this->switcherHtml($html));
        Http::assertNothingSent();
    }

    // ── Assets, policy, markup ──────────────────────────────────────────────

    public function test_the_shared_assets_come_from_this_origin_and_the_strict_library_policy_allows_them(): void
    {
        $this->fakeInfoDot([self::url(self::ACME) => Http::response(self::body(self::ACME))]);
        $user = $this->memberOf(self::ACME, 'Acme Ltd');

        foreach ([$this->get(route('intel.find')), $this->actingAs($user)->get(route('me.home'))] as $response) {
            $html = $response->assertOk()->getContent();
            $csp = $response->headers->get('Content-Security-Policy');

            $this->assertStringContainsString("script-src 'self';", $csp);
            $this->assertStringContainsString("style-src 'self';", $csp);
            $this->assertStringNotContainsString('unsafe-inline', $csp, 'the strict policy stays strict');
            $this->assertStringContainsString('href="/dot-ui/css/dot-search.css"', $html);
            $this->assertDotUiReferencesAreOwnFiles($html);
        }

        $workspace = $this->actingAs($user)->get(route('workspace.figures'));
        $this->assertDotUiReferencesAreOwnFiles($workspace->getContent());
        $this->assertStringContainsString("script-src 'self' 'unsafe-inline' 'unsafe-eval'", $workspace->headers->get('Content-Security-Policy'));
    }

    private function assertDotUiReferencesAreOwnFiles(string $html): void
    {
        preg_match_all('/(?:href|src)="(\/dot-ui\/[^"]+)"/', $html, $m);
        $this->assertNotEmpty($m[1]);
        foreach ($m[1] as $path) {
            $this->assertFileExists(public_path($path), $path.' is served from this app');
        }
        preg_match_all('/(?:href|src)="(https?:\/\/[^"]*dot-ui[^"]*)"/', $html, $far);
        $this->assertSame([], $far[1], 'no shared asset is loaded from another origin');
    }

    public function test_the_shared_markup_has_no_inline_style_or_script(): void
    {
        $this->fakeInfoDot([self::url(self::ACME) => Http::response(self::body(self::ACME))]);
        $user = $this->memberOf(self::ACME, 'Acme Ltd');

        $library = $this->actingAs($user)->get(route('me.home'))->getContent();
        $this->assertStringNotContainsString(' style="', $library, 'the strict policy has no style-src-attr');
        $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*\bsrc=)(?![^>]*application\/ld\+json)/', $library);

        $workspace = $this->actingAs($user)->get(route('workspace.figures'))->getContent();
        $this->assertStringNotContainsString(' style="', $this->switcherHtml($workspace));
        $this->assertSame(1, preg_match('/<div class="ws-search">.*?<\/form>/s', $workspace, $pill));
        $this->assertStringNotContainsString(' style="', $pill[0]);
        $this->assertMatchesRegularExpression('/<div class="ws-search">\s*<div class="dsr/', $workspace);
    }

    // ── What this PR must not change ────────────────────────────────────────

    public function test_a_company_that_lost_the_platform_is_still_refused_and_sees_no_header(): void
    {
        $body = self::body(self::ACME, ['infodot']);
        $this->fakeInfoDot([self::url(self::ACME) => Http::response($body)]);

        $this->actingAs($this->memberOf(self::ACME, 'Acme Ltd'))->get(route('workspace.figures'))->assertForbidden()->assertDontSee('data-dsw', false);
    }

    public function test_the_operate_area_keeps_its_staff_gate(): void
    {
        $this->fakeInfoDot([self::url(self::ACME) => Http::response(self::body(self::ACME))]);

        $this->actingAs($this->memberOf(self::ACME, 'Acme Ltd'))->get(route('me.operate'))->assertForbidden();
    }
}
