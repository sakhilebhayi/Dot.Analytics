<?php

namespace Tests\Feature;

use App\Livewire\Analytics\HeaderSearch;
use App\Models\AnalyticsAlert;
use App\Models\AnalyticsReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\BuildsTeams;
use Tests\TestCase;

/** R-52: Roboto, the shared dot-ui header on every page (signed in and public), and the live header search. */
class R52HeaderTest extends TestCase
{
    use BuildsTeams, RefreshDatabase;

    private const TERM = 'Zorbaglub';

    protected function setUp(): void
    {
        parent::setUp();
        config(['ecosystem.sso_only' => false, 'scout.driver' => 'database']);
    }

    public function test_every_signed_in_page_type_carries_the_header_with_search_menu_button_and_grouped_nav(): void
    {
        $user = $this->ownerWithTeam();

        foreach (['/dashboard', '/workspace/figures', '/workspace/insights', '/workspace/briefing', '/workspace/ask', '/workspace/reports', '/workspace/sources', '/search', '/user/profile', '/teams/'.$user->currentTeam->id] as $path) {
            $html = $this->actingAs($user)->get($path)->assertOk()->getContent();
            $this->assertStringContainsString('class="dh', $html, $path);
            $this->assertStringContainsString('class="dh-search"', $html, "$path: the search pill is on every page");
            $this->assertStringContainsString('data-dh-search-toggle', $html, "$path: the phone search button");
            $this->assertStringContainsString('data-dh-menu-button', $html, "$path: the phone menu button");
            $this->assertStringContainsString('id="dh-drawer"', $html, "$path: the shared drawer");
            $this->assertStringContainsString('Explore', $html, "$path: secondary items are grouped");
            $this->assertStringNotContainsString('<details class="ws-menu"', $html, "$path: the bare details drawer is gone");
        }
    }

    public function test_the_login_page_and_the_public_library_use_roboto_and_the_shared_header(): void
    {
        $login = $this->get('/login')->assertOk()->getContent();
        $this->assertStringContainsString('/dot-ui/css/dot-roboto.css', $login);
        $this->assertStringNotContainsString('Figtree', $login);

        $home = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('class="dh', $home);
        $this->assertStringNotContainsString('class="top"', $home, 'the separate public header is gone');
        $this->assertStringContainsString('data-dh-menu-button', $home);
        $this->assertStringContainsString("style-src 'self';", $this->get('/')->headers->get('Content-Security-Policy'));
        $this->assertStringNotContainsString(' style="', substr($home, 0, strpos($home, '<main')), 'the public header is class-only under the strict policy');
    }

    public function test_roboto_is_declared_once_served_from_this_origin_and_nothing_loads_a_third_party_font(): void
    {
        $this->assertFileExists(public_path('dot-ui/fonts/roboto/roboto-latin-wght-normal.woff2'));
        $this->assertDirectoryDoesNotExist(public_path('fonts'), 'the IBM Plex files are gone');

        $declared = 0;
        foreach (['resources/css', 'public/dot-ui/css'] as $dir) {
            foreach (glob(base_path($dir).'/*.css') as $file) {
                $css = file_get_contents($file);
                $declared += preg_match_all('/@font-face/', $css);
                $this->assertStringNotContainsString('IBM Plex', $css, basename($file));
                if ($dir === 'resources/css') {
                    $this->assertStringNotContainsString('Archivo', $css, basename($file));
                }
                $this->assertStringNotContainsString('fonts.googleapis', $css, basename($file));
            }
        }
        $this->assertSame(3, $declared, 'Roboto @font-face (latin, latin-ext, fallback), declared only in dot-roboto.css');

        foreach (['/dashboard', '/workspace/figures'] as $path) {
            $html = $this->actingAs($this->ownerWithTeam())->get($path)->getContent();
            $this->assertDoesNotMatchRegularExpression('#https?://[^"\']*(fonts\.g|bunny|gstatic)#', $html);
            $this->assertStringContainsString('class="dot-roboto"', $html);
        }
    }

    public function test_no_inline_script_or_style_in_the_shared_header_markup(): void
    {
        $html = $this->actingAs($this->ownerWithTeam())->get('/dashboard')->getContent();
        $header = substr($html, strpos($html, '<header'), strpos($html, '</header>') - strpos($html, '<header'));
        $this->assertStringNotContainsString(' style="', $header);
        $this->assertStringNotContainsString('<script', $header);
    }

    // ── The live header search ──────────────────────────────────────────────

    public function test_the_header_search_finds_this_companys_content_and_never_another(): void
    {
        $acme = $this->ownerWithTeam();
        $globex = $this->ownerWithTeam();
        AnalyticsAlert::factory()->create(['team_id' => $acme->currentTeam->id, 'title' => self::TERM.' Acme alert']);
        AnalyticsAlert::factory()->create(['team_id' => $globex->currentTeam->id, 'title' => self::TERM.' Globex alert']);
        AnalyticsReport::factory()->create(['team_id' => $globex->currentTeam->id, 'title' => self::TERM.' Globex report']);
        $this->connect($globex->currentTeam, 'tasks')->update(['display_name' => self::TERM.' Globex feed']);

        Livewire::actingAs($acme)->test(HeaderSearch::class)->set('query', self::TERM)
            ->assertSee(self::TERM.' Acme alert')
            ->assertDontSee('Globex');

        Livewire::actingAs($globex)->test(HeaderSearch::class)->set('query', self::TERM)
            ->assertSee('Globex alert')->assertSee('Globex report')->assertSee('Globex feed')
            ->assertDontSee('Acme');
    }

    public function test_the_header_search_covers_figures_and_sources_of_connected_platforms_only(): void
    {
        $owner = $this->ownerWithTeam();
        $this->connect($owner->currentTeam, 'tasks');

        Livewire::actingAs($owner)->test(HeaderSearch::class)->set('query', 'Open tasks')
            ->assertSee('Figures')->assertSee('Open tasks');
        Livewire::actingAs($owner)->test(HeaderSearch::class)->set('query', 'Headcount')
            ->assertSee('Nothing in your workspace matches');
    }

    public function test_every_team_role_may_search_and_a_user_without_a_team_gets_nothing(): void
    {
        $owner = $this->ownerWithTeam();
        AnalyticsAlert::factory()->create(['team_id' => $owner->currentTeam->id, 'title' => self::TERM.' shared alert']);

        foreach (['admin', 'editor', 'viewer'] as $role) {
            Livewire::actingAs($this->memberOf($owner, $role))->test(HeaderSearch::class)->set('query', self::TERM)->assertSee('shared alert');
        }

        $loner = User::factory()->create(['current_team_id' => null]);
        Livewire::actingAs($loner)->test(HeaderSearch::class)->set('query', self::TERM)->assertDontSee('shared alert');
    }

    public function test_a_stale_current_team_the_user_left_returns_nothing(): void
    {
        $owner = $this->ownerWithTeam();
        $other = $this->ownerWithTeam();
        AnalyticsAlert::factory()->create(['team_id' => $owner->currentTeam->id, 'title' => self::TERM.' private']);
        $other->forceFill(['current_team_id' => $owner->currentTeam->id])->save();

        Livewire::actingAs($other->fresh())->test(HeaderSearch::class)->set('query', self::TERM)->assertDontSee('private');
    }

    public function test_empty_short_and_no_match_states_and_the_full_page_link(): void
    {
        $owner = $this->ownerWithTeam();
        $c = Livewire::actingAs($owner)->test(HeaderSearch::class);

        $c->assertSee('Type two letters')->set('query', 'a')->assertSee('Type two letters');
        $c->set('query', 'qqqqzzzz')->assertSee('Nothing in your workspace matches')->assertSee('See all results');
    }

    public function test_the_search_term_is_treated_as_data_not_as_sql_wildcards_or_markup(): void
    {
        $owner = $this->ownerWithTeam();
        $this->connect($owner->currentTeam, 'tasks');

        Livewire::actingAs($owner)->test(HeaderSearch::class)->set('query', '%%')->assertSee('Nothing in your workspace matches');
        Livewire::actingAs($owner)->test(HeaderSearch::class)->set('query', '<script>alert(1)</script>')->assertDontSee('<script>alert', false);
    }
}
