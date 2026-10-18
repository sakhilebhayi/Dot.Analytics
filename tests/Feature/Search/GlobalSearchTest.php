<?php

namespace Tests\Feature\Search;

use App\Livewire\Analytics\SearchPanel;
use App\Models\AnalyticsAlert;
use App\Models\AnalyticsDashboard;
use App\Models\AnalyticsReport;
use App\Models\CrossPlatformInsight;
use App\Models\DataSource;
use App\Models\IntelligenceNode;
use App\Models\Recommendation;
use App\Models\Team;
use App\Models\User;
use App\Services\Search\GlobalSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BuildsTeams;
use Tests\TestCase;

/**
 * Search is read-only, so every team role may use it, and it never crosses a company boundary:
 * not through the global scope (which only exists for a signed-in user), not through a numeric
 * query matching another company's primary key, not through a stale "current team".
 */
class GlobalSearchTest extends TestCase
{
    use BuildsTeams, RefreshDatabase;

    private const TERM = 'Zorbaglub';

    private const TYPES = ['nodes', 'insights', 'alerts', 'recommendations', 'reports', 'dashboards'];

    private User $owner;

    private Team $team;

    private User $outsider;

    private Team $otherTeam;

    protected function setUp(): void
    {
        parent::setUp();

        config(['scout.driver' => 'database']);

        $this->owner = $this->ownerWithTeam();
        $this->team = $this->owner->currentTeam;
        $this->outsider = $this->ownerWithTeam();
        $this->otherTeam = $this->outsider->currentTeam;
    }

    /** One row of every searchable type, the term smuggled into every searchable column. */
    private function seedEverywhere(Team $team, string $term = self::TERM, ?User $author = null): void
    {
        $author ??= $team->owner;

        IntelligenceNode::factory()->create(['team_id' => $team->id, 'label' => "{$term} node", 'entity_type' => "{$term}-type", 'entity_id' => "{$term}-001"]);
        CrossPlatformInsight::factory()->create(['team_id' => $team->id, 'title' => "{$term} insight", 'narrative' => "narrative {$term}"]);
        AnalyticsAlert::factory()->create(['team_id' => $team->id, 'title' => "{$term} alert", 'description' => "description {$term}"]);
        Recommendation::factory()->create(['team_id' => $team->id, 'title' => "{$term} recommendation", 'rationale' => "rationale {$term}"]);
        AnalyticsReport::factory()->create(['team_id' => $team->id, 'user_id' => $author->id, 'title' => "{$term} report", 'description' => "description {$term}"]);
        AnalyticsDashboard::factory()->create(['team_id' => $team->id, 'user_id' => $author->id, 'title' => "{$term} dashboard"]);
    }

    /** @return array<string, int> result count per type */
    private function counts(User $user, string $query): array
    {
        $results = app(GlobalSearch::class)->search($user, $query);

        return array_map(fn ($collection) => $collection->count(), $results);
    }

    /** @return array<string, array{string}> */
    public static function roles(): array
    {
        return ['owner' => ['owner'], 'admin' => ['admin'], 'editor' => ['editor'], 'viewer' => ['viewer']];
    }

    #[DataProvider('roles')]
    public function test_every_role_finds_every_type_of_its_own_team(string $role): void
    {
        $this->seedEverywhere($this->team);
        $user = $role === 'owner' ? $this->owner : $this->memberOf($this->owner, $role);

        $this->assertSame(array_fill_keys(self::TYPES, 1), $this->counts($user, self::TERM));
    }

    public function test_another_companys_rows_never_appear_even_when_every_column_carries_the_term(): void
    {
        $this->seedEverywhere($this->team);
        $this->seedEverywhere($this->otherTeam, self::TERM.' FOREIGN');

        $results = app(GlobalSearch::class)->search($this->owner, self::TERM);

        foreach ($results as $type => $rows) {
            $this->assertCount(1, $rows, $type);
            $this->assertSame($this->team->id, $rows->first()->team_id, $type);
            $this->assertStringNotContainsString('FOREIGN', json_encode($rows->first()->toArray()), $type);
        }
    }

    public function test_isolation_does_not_depend_on_the_global_scope_of_a_signed_in_user(): void
    {
        $this->seedEverywhere($this->otherTeam, self::TERM.' FOREIGN');
        $this->seedEverywhere($this->team);

        // Nobody is signed in: HasTeamScope has no user to scope to and applies nothing.
        $this->assertGuest();

        $this->assertSame(array_fill_keys(self::TYPES, 1), $this->counts($this->owner, self::TERM));
        $this->assertSame(array_fill_keys(self::TYPES, 1), $this->counts($this->outsider, 'FOREIGN'));
    }

    public function test_a_team_with_nothing_finds_nothing_while_another_team_has_everything(): void
    {
        $this->seedEverywhere($this->otherTeam);

        $this->assertSame(array_fill_keys(self::TYPES, 0), $this->counts($this->owner, self::TERM));
    }

    public function test_a_numeric_query_cannot_reach_another_companys_row_by_primary_key(): void
    {
        $foreign = Recommendation::factory()->create(['team_id' => $this->otherTeam->id, 'title' => 'Plain foreign title']);
        $foreignAlert = AnalyticsAlert::factory()->create(['team_id' => $this->otherTeam->id, 'title' => 'Another plain title']);

        $this->assertSame(0, $this->counts($this->owner, (string) $foreign->id)['recommendations']);
        $this->assertSame(0, $this->counts($this->owner, (string) $foreignAlert->id)['alerts']);
    }

    public function test_a_wildcard_query_still_stays_inside_the_team(): void
    {
        $this->seedEverywhere($this->team);
        $this->seedEverywhere($this->otherTeam, 'Other');

        foreach (['%%', '__', '%_'] as $wild) {
            foreach (app(GlobalSearch::class)->search($this->owner, $wild) as $type => $rows) {
                $this->assertTrue($rows->every(fn ($row) => $row->team_id === $this->team->id), "{$type} for {$wild}");
            }
        }
    }

    public function test_a_stale_current_team_the_user_does_not_belong_to_finds_nothing(): void
    {
        $this->seedEverywhere($this->otherTeam);
        $stranger = User::factory()->create(['current_team_id' => $this->otherTeam->id]);
        $this->assertFalse($stranger->belongsToTeam($this->otherTeam));

        $this->assertSame(array_fill_keys(self::TYPES, 0), $this->counts($stranger, self::TERM));
    }

    public function test_a_user_without_any_team_finds_nothing(): void
    {
        $this->seedEverywhere($this->team);
        $loner = User::factory()->create();

        $this->assertSame(array_fill_keys(self::TYPES, 0), $this->counts($loner, self::TERM));
    }

    public function test_switching_team_switches_what_is_searched(): void
    {
        $this->seedEverywhere($this->team, 'Alpha');
        $this->seedEverywhere($this->otherTeam, 'Beta');
        $this->otherTeam->users()->attach($this->owner, ['role' => 'viewer']);

        $this->assertSame(1, $this->counts($this->owner, 'Alpha')['alerts']);
        $this->assertSame(0, $this->counts($this->owner, 'Beta')['alerts']);

        $this->owner->switchTeam($this->otherTeam);
        $owner = $this->owner->fresh();

        $this->assertSame(0, $this->counts($owner, 'Alpha')['alerts']);
        $this->assertSame(1, $this->counts($owner, 'Beta')['alerts']);
    }

    public function test_matching_is_case_insensitive(): void
    {
        $this->seedEverywhere($this->team);

        $this->assertSame(array_fill_keys(self::TYPES, 1), $this->counts($this->owner, strtolower(self::TERM)));
        $this->assertSame(array_fill_keys(self::TYPES, 1), $this->counts($this->owner, strtoupper(self::TERM)));
    }

    public function test_a_query_shorter_than_two_characters_searches_nothing(): void
    {
        $this->seedEverywhere($this->team);

        $this->assertSame(array_fill_keys(self::TYPES, 0), $this->counts($this->owner, 'Z'));
        $this->assertSame(array_fill_keys(self::TYPES, 0), $this->counts($this->owner, '  Z  '));
        $this->assertSame(array_fill_keys(self::TYPES, 0), $this->counts($this->owner, ''));
    }

    public function test_surrounding_whitespace_is_ignored_and_a_huge_query_is_cut_down(): void
    {
        $this->seedEverywhere($this->team);

        $this->assertSame(array_fill_keys(self::TYPES, 1), $this->counts($this->owner, '   '.self::TERM.'   '));
        // Cut to 100 characters, so a 5,000 character query neither errors nor matches.
        $this->assertSame(array_fill_keys(self::TYPES, 0), $this->counts($this->owner, str_repeat('x', 5000)));
    }

    public function test_each_type_is_capped_at_ten_results_newest_first(): void
    {
        foreach (range(1, 15) as $i) {
            Recommendation::factory()->create(['team_id' => $this->team->id, 'title' => "Reduce idle time {$i}"]);
        }

        $rows = app(GlobalSearch::class)->search($this->owner, 'idle time')['recommendations'];

        $this->assertCount(10, $rows);
        $this->assertSame('Reduce idle time 15', $rows->first()->title);
    }

    public function test_dashboards_are_team_wide_so_a_colleagues_dashboard_is_found(): void
    {
        $editor = $this->memberOf($this->owner, 'editor');
        AnalyticsDashboard::factory()->create(['team_id' => $this->team->id, 'user_id' => $this->owner->id, 'title' => 'Ops Overview']);

        $titles = app(GlobalSearch::class)->search($editor, 'Ops')['dashboards']->pluck('title');

        $this->assertSame(['Ops Overview'], $titles->all());
    }

    // ─── The Livewire panel and the page ────────────────────────────────────

    public function test_the_panel_shows_a_viewer_their_teams_results_and_never_another_companys(): void
    {
        $viewer = $this->memberOf($this->owner, 'viewer');
        $this->seedEverywhere($this->team);
        $this->seedEverywhere($this->otherTeam, self::TERM.' FOREIGN');

        $component = Livewire::actingAs($viewer)->test(SearchPanel::class)->set('query', self::TERM);

        foreach (self::TYPES as $type) {
            $this->assertCount(1, $component->get($type), $type);
        }
        $component->assertSee(self::TERM.' alert')->assertDontSee('FOREIGN');
    }

    public function test_the_page_renders_a_viewers_results_and_hides_another_companys(): void
    {
        $viewer = $this->memberOf($this->owner, 'viewer');
        $this->seedEverywhere($this->team);
        $this->seedEverywhere($this->otherTeam, self::TERM.' FOREIGN');

        $this->actingAs($viewer)->get(route('search', ['q' => self::TERM]))
            ->assertOk()
            ->assertSee(self::TERM.' insight')
            ->assertSee(self::TERM.' dashboard')
            ->assertDontSee('FOREIGN');
    }

    public function test_searching_for_a_term_that_exists_only_elsewhere_says_no_results(): void
    {
        $this->seedEverywhere($this->otherTeam, 'Quixotic');

        $this->actingAs($this->owner)->get(route('search', ['q' => 'Quixotic']))
            ->assertOk()
            ->assertSee('No results for')
            ->assertDontSee('Quixotic node');
    }

    public function test_the_query_is_echoed_escaped(): void
    {
        $this->actingAs($this->owner)->get(route('search', ['q' => '<script>alert(1)</script>']))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_the_nav_bar_search_box_is_on_authenticated_pages(): void
    {
        $this->actingAs($this->owner)->get('/dashboard')
            ->assertOk()
            ->assertSee('action="'.route('search').'"', false);
    }

    // ─── Nothing sensitive is searchable ────────────────────────────────────

    public function test_secrets_and_raw_payloads_are_not_reachable_through_search(): void
    {
        $source = DataSource::factory()->create(['team_id' => $this->team->id, 'platform' => 'tasks', 'status' => 'connected', 'config' => ['api_key' => 'sk-cfg-smuggled']]);
        $source->rotateWebhookSecret();
        $source->forceFill(['webhook_secret' => 'whsec-smuggled-secret'])->save();
        AnalyticsAlert::factory()->create(['team_id' => $this->team->id, 'title' => 'Plain', 'description' => 'Plain', 'context' => ['api_key' => 'sk-ctx-smuggled', 'window' => ['values' => [['value' => 'ctx-payload-smuggled']]]]]);
        CrossPlatformInsight::factory()->create(['team_id' => $this->team->id, 'title' => 'Plain', 'narrative' => 'Plain', 'provenance' => ['note' => 'prov-smuggled'], 'supporting_metrics' => ['m' => 'metrics-smuggled']]);
        IntelligenceNode::factory()->create(['team_id' => $this->team->id, 'label' => 'Plain', 'attributes' => ['token' => 'attr-smuggled']]);
        Recommendation::factory()->create(['team_id' => $this->team->id, 'title' => 'Plain', 'rationale' => 'Plain', 'supporting_data' => ['k' => 'data-smuggled'], 'action_url' => 'https://example.test/action-smuggled']);
        AnalyticsReport::factory()->create(['team_id' => $this->team->id, 'user_id' => $this->owner->id, 'title' => 'Plain', 'config' => ['report_type' => 'insights', 'x' => 'cfg-smuggled']]);

        foreach (['smuggled', 'whsec', 'sk-cfg', 'sk-ctx'] as $needle) {
            $this->assertSame(array_fill_keys(self::TYPES, 0), $this->counts($this->owner, $needle), $needle);
        }
    }
}
