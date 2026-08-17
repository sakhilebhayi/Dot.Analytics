# Global Search Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Wire up the already-installed-but-unused `laravel/scout` dependency to power a `/search` page finding knowledge graph entities, cross-platform insights, alerts, recommendations, saved reports, and dashboards, reachable from a nav-bar search box on every page.

**Architecture:** Scout's zero-infrastructure `database` engine (plain `LIKE` against each model's own table via `toSearchableArray()`) — no external search service, no index-sync job. Six models get `Searchable`; five inherit team isolation for free from the `HasTeamScope` global scope every one of them already carries, and `AnalyticsDashboard` gets one extra PHP-side filter (reusing `AnalyticsDashboardPolicy`'s own private/team rule) since Scout's fluent query builder can't express that OR condition directly. A new `SearchPanel` Livewire component holds six independent `#[Computed]` properties (matching `DashboardBuilderPanel`'s own shape, not a shared service abstraction this codebase doesn't otherwise use), rendered on a new `/search` page that follows the exact `/dashboards`-established route/view/component pattern. A plain GET form in the nav bar is the only new JS-free entry point.

**Tech Stack:** Laravel 13, Livewire 3, `laravel/scout` `v10.25.0` (already a direct dependency, unconfigured), PHPUnit, Jetstream teams.

## Global Constraints

- Spec: `docs/superpowers/specs/2026-08-17-global-search-design.md` — read it first.
- No external search service. `SCOUT_DRIVER=database` only.
- No new per-entity "show" pages/routes — none exist today for any of the six searchable types (confirmed during spec research); results are self-contained on the search page and link back to the existing page each type already lives on (`/dashboard` or `/dashboards`).
- `toSearchableArray()` on every model returns only real, existing columns — no synthetic fields, no new migrations.
- Results capped at 10 per entity type. No pagination.
- A query under 2 characters returns no results from any computed property (avoids matching everything on an empty/near-empty query).
- Run `vendor/bin/pint --dirty --format agent` after every task, before committing.
- This branch (`feat/global-search`) is based on `feat/custom-dashboards`, not `feature/ecosystem-sso` directly — the `AnalyticsDashboard` search integration genuinely depends on `AnalyticsDashboardPolicy` and the `visibility` column added there, neither of which exists on `feature/ecosystem-sso` until that PR lands.

---

### Task 1: Wire up Scout + `Searchable` on `IntelligenceNode` and `AnalyticsReport`

**Files:**
- Modify: `.env`, `.env.example` (add `SCOUT_DRIVER=database`)
- Create: `config/scout.php` (via `php artisan vendor:publish --tag=scout-config`)
- Modify: `app/Models/IntelligenceNode.php`, `app/Models/AnalyticsReport.php`
- Create: `database/factories/IntelligenceNodeFactory.php`, `database/factories/AnalyticsReportFactory.php`
- Test: `tests/Feature/Models/SearchableModelsTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `IntelligenceNode::search()`, `AnalyticsReport::search()`, both factories — consumed by Task 3's `SearchPanel`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Models/SearchableModelsTest.php`:

```php
<?php

namespace Tests\Feature\Models;

use App\Models\AnalyticsReport;
use App\Models\IntelligenceNode;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchableModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_intelligence_node_search_finds_a_match_by_label(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);

        IntelligenceNode::factory()->create(['team_id' => $user->currentTeam->id, 'label' => 'Acme Logistics Ltd']);
        IntelligenceNode::factory()->create(['team_id' => $user->currentTeam->id, 'label' => 'Unrelated Entity']);

        $results = IntelligenceNode::search('Acme')->get();

        $this->assertCount(1, $results);
        $this->assertSame('Acme Logistics Ltd', $results->first()->label);
    }

    public function test_intelligence_node_search_never_returns_another_teams_row(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $otherTeam = Team::factory()->create();
        IntelligenceNode::factory()->create(['team_id' => $otherTeam->id, 'label' => 'Acme Foreign Entity']);

        $this->actingAs($user);

        $results = IntelligenceNode::search('Acme')->get();

        $this->assertCount(0, $results);
    }

    public function test_analytics_report_search_finds_a_match_by_title(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);

        AnalyticsReport::factory()->create([
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'title' => 'Weekly Risk Summary',
        ]);
        AnalyticsReport::factory()->create([
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'title' => 'Unrelated Report',
        ]);

        $results = AnalyticsReport::search('Risk')->get();

        $this->assertCount(1, $results);
        $this->assertSame('Weekly Risk Summary', $results->first()->title);
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Models/SearchableModelsTest.php`
Expected: FAIL — `Searchable::search()` isn't defined on either model yet, and neither factory exists yet.

- [ ] **Step 3: Publish Scout's config and set the driver**

```bash
php artisan vendor:publish --tag=scout-config
```

In `.env` and `.env.example`, add:

```
SCOUT_DRIVER=database
```

- [ ] **Step 4: Add `Searchable` to `IntelligenceNode`**

In `app/Models/IntelligenceNode.php`:

```php
<?php

namespace App\Models;

use App\Models\Concerns\HasTeamScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

class IntelligenceNode extends Model
{
    use HasFactory, HasTeamScope, Searchable;

    protected $fillable = [
        'team_id', 'entity_type', 'entity_id', 'label', 'source_platform', 'attributes',
    ];

    protected $casts = [
        'attributes' => 'array',
    ];

    public function toSearchableArray(): array
    {
        return [
            'label' => $this->label,
            'entity_type' => $this->entity_type,
            'entity_id' => $this->entity_id,
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function outgoingEdges(): HasMany
    {
        return $this->hasMany(IntelligenceEdge::class, 'from_node_id');
    }

    public function incomingEdges(): HasMany
    {
        return $this->hasMany(IntelligenceEdge::class, 'to_node_id');
    }
}
```

- [ ] **Step 5: Add `Searchable` to `AnalyticsReport`**

Read `app/Models/AnalyticsReport.php` first to confirm its current full contents (fillable/casts/relations), then add the same two-line change (`use ... Searchable;` in the `use` statement inside the class, `Laravel\Scout\Searchable` import) plus:

```php
public function toSearchableArray(): array
{
    return [
        'title' => $this->title,
        'description' => $this->description,
    ];
}
```

- [ ] **Step 6: Write the two new factories**

Create `database/factories/IntelligenceNodeFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\IntelligenceNode;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IntelligenceNode>
 */
class IntelligenceNodeFactory extends Factory
{
    protected $model = IntelligenceNode::class;

    public function definition(): array
    {
        $types = ['customer', 'invoice', 'equipment', 'operator', 'contract', 'order', 'ticket', 'project', 'supplier', 'asset', 'employee'];

        return [
            'team_id' => Team::factory(),
            'entity_type' => $this->faker->randomElement($types),
            'entity_id' => strtoupper($this->faker->bothify('???-####')),
            'label' => $this->faker->company(),
            'source_platform' => $this->faker->randomElement(['dot.fleet', 'dot.crm', 'dot.hr']),
            'attributes' => [],
        ];
    }
}
```

Create `database/factories/AnalyticsReportFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\AnalyticsReport;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnalyticsReport>
 */
class AnalyticsReportFactory extends Factory
{
    protected $model = AnalyticsReport::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'user_id' => User::factory(),
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->sentence(),
            'type' => 'ad_hoc',
            'config' => ['report_type' => $this->faker->randomElement(['insights', 'alerts', 'recommendations', 'metrics'])],
        ];
    }
}
```

The `entity_type` list matches `knowledge-graph-panel.blade.php`'s own dropdown exactly (confirmed by reading it during spec research) — not an arbitrary invented list. `type: 'ad_hoc'` and the `config['report_type']` shape match `SavedReportsPanel::create()`'s exact real values (confirmed by reading that method directly), not guessed.

- [ ] **Step 7: Run the tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Models/SearchableModelsTest.php`
Expected: PASS, 3 tests, 0 failures.

- [ ] **Step 8: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `passed`.

- [ ] **Step 9: Commit**

```bash
git add .env.example config/scout.php app/Models/IntelligenceNode.php app/Models/AnalyticsReport.php \
  database/factories/IntelligenceNodeFactory.php database/factories/AnalyticsReportFactory.php \
  tests/Feature/Models/SearchableModelsTest.php
git commit -m "$(cat <<'EOF'
feat: wire up Scout's database engine, starting with 2 models

laravel/scout has been a direct dependency (v10.25.0) with zero
configuration this whole time -- no config/scout.php, no SCOUT_DRIVER,
no model using Searchable (confirmed by grep during spec research).
Publishes the config, sets the zero-infrastructure database engine
(plain LIKE against each model's own table, no external service), and
adds Searchable to the two models that needed brand-new factories
(IntelligenceNode, AnalyticsReport) as the first proof this actually
works end to end.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

Note: `.env` itself is typically gitignored in this repo (confirmed by
this repo's own earlier removal of `.env.testing` from version control) --
only stage it if `git status` shows it as tracked; `.env.example` is the
one that must be committed.

---

### Task 2: `Searchable` on the remaining four models

**Files:**
- Modify: `app/Models/CrossPlatformInsight.php`, `app/Models/AnalyticsAlert.php`, `app/Models/Recommendation.php`, `app/Models/AnalyticsDashboard.php`
- Test: `tests/Feature/Models/SearchableModelsTest.php` (extend)

**Interfaces:**
- Consumes: existing factories for all four (`CrossPlatformInsightFactory`, `AnalyticsAlertFactory`, `RecommendationFactory`, `AnalyticsDashboardFactory` — all already exist, confirmed during spec research).
- Produces: `CrossPlatformInsight::search()`, `AnalyticsAlert::search()`, `Recommendation::search()`, `AnalyticsDashboard::search()` — consumed by Task 3.

- [ ] **Step 1: Write the failing tests**

Extend `tests/Feature/Models/SearchableModelsTest.php`, adding these methods
inside the existing class:

```php
    public function test_cross_platform_insight_search_finds_a_match_by_title(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);

        \App\Models\CrossPlatformInsight::factory()->create([
            'team_id' => $user->currentTeam->id,
            'title' => 'Productivity dropped 8% this month',
        ]);

        $results = \App\Models\CrossPlatformInsight::search('Productivity')->get();

        $this->assertCount(1, $results);
    }

    public function test_analytics_alert_search_finds_a_match_by_description(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);

        \App\Models\AnalyticsAlert::factory()->create([
            'team_id' => $user->currentTeam->id,
            'title' => 'Generic Alert',
            'description' => 'Overtime costs spiked in the fleet division',
        ]);

        $results = \App\Models\AnalyticsAlert::search('Overtime')->get();

        $this->assertCount(1, $results);
    }

    public function test_recommendation_search_finds_a_match_by_title(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);

        \App\Models\Recommendation::factory()->create([
            'team_id' => $user->currentTeam->id,
            'title' => 'Reassign three certified operators',
        ]);

        $results = \App\Models\Recommendation::search('operators')->get();

        $this->assertCount(1, $results);
    }

    public function test_analytics_dashboard_search_finds_a_match_and_stays_team_scoped(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $otherTeam = \App\Models\Team::factory()->create();
        \App\Models\AnalyticsDashboard::factory()->create(['team_id' => $otherTeam->id, 'title' => 'Ops Overview Foreign']);

        $this->actingAs($user);
        \App\Models\AnalyticsDashboard::factory()->create(['team_id' => $user->currentTeam->id, 'user_id' => $user->id, 'title' => 'Ops Overview']);

        $results = \App\Models\AnalyticsDashboard::search('Ops Overview')->get();

        $this->assertCount(1, $results);
        $this->assertSame($user->currentTeam->id, $results->first()->team_id);
    }
```

Note: `test_analytics_dashboard_search_finds_a_match_and_stays_team_scoped`
only proves team isolation (via `HasTeamScope`, already inherited) — it
does **not** test the private/team visibility rule, since that filter lives
in `SearchPanel` (Task 3), not on the model. Task 3's own tests cover that.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Models/SearchableModelsTest.php`
Expected: the 4 new tests FAIL (`search()` undefined on all four models); the
3 from Task 1 still PASS.

- [ ] **Step 3: Add `Searchable` to all four models**

`app/Models/CrossPlatformInsight.php` — add `Laravel\Scout\Searchable` to
the `use` statement and the trait list, add:

```php
public function toSearchableArray(): array
{
    return [
        'title' => $this->title,
        'narrative' => $this->narrative,
    ];
}
```

`app/Models/AnalyticsAlert.php` — same pattern:

```php
public function toSearchableArray(): array
{
    return [
        'title' => $this->title,
        'description' => $this->description,
    ];
}
```

`app/Models/Recommendation.php` — same pattern:

```php
public function toSearchableArray(): array
{
    return [
        'title' => $this->title,
        'rationale' => $this->rationale,
    ];
}
```

`app/Models/AnalyticsDashboard.php` — same pattern:

```php
public function toSearchableArray(): array
{
    return [
        'title' => $this->title,
    ];
}
```

Read each file first to confirm its current exact contents before editing,
since none of the four have been read fresh in this plan (only their
`fillable` arrays were confirmed during spec research) — add `Searchable`
alongside whatever traits each already uses, don't remove anything.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Models/SearchableModelsTest.php`
Expected: PASS, 7 tests, 0 failures.

- [ ] **Step 5: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `passed`.

- [ ] **Step 6: Commit**

```bash
git add app/Models/CrossPlatformInsight.php app/Models/AnalyticsAlert.php \
  app/Models/Recommendation.php app/Models/AnalyticsDashboard.php \
  tests/Feature/Models/SearchableModelsTest.php
git commit -m "$(cat <<'EOF'
feat: Searchable on insights, alerts, recommendations, dashboards

Completes all 6 models Global Search needs. Each already carries
HasTeamScope, so team isolation is automatic -- AnalyticsDashboard's
extra private/team visibility rule is deliberately not handled here;
it lives in SearchPanel (next commit), since Scout's own query builder
can't express that OR condition directly (see the spec's Design
section for why).

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 3: `SearchPanel` Livewire component

**Files:**
- Create: `app/Livewire/Analytics/SearchPanel.php`
- Test: `tests/Feature/Livewire/SearchPanelTest.php`

**Interfaces:**
- Consumes: all 6 `Searchable` models (Tasks 1–2).
- Produces: `nodes`/`insights`/`alerts`/`recommendations`/`reports`/`dashboards` computed properties, `livewire.analytics.search-panel` view target — consumed by Task 4's view.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Livewire/SearchPanelTest.php`:

```php
<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Analytics\SearchPanel;
use App\Models\AnalyticsAlert;
use App\Models\AnalyticsDashboard;
use App\Models\CrossPlatformInsight;
use App\Models\IntelligenceNode;
use App\Models\Recommendation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SearchPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_one_character_query_returns_nothing_from_every_type(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        IntelligenceNode::factory()->create(['team_id' => $user->currentTeam->id, 'label' => 'A']);

        $component = Livewire::actingAs($user)->test(SearchPanel::class)->set('query', 'A');

        $this->assertTrue($component->get('nodes')->isEmpty());
    }

    public function test_a_real_query_finds_matches_across_multiple_types_simultaneously(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        IntelligenceNode::factory()->create(['team_id' => $user->currentTeam->id, 'label' => 'Meridian Freight']);
        AnalyticsAlert::factory()->create(['team_id' => $user->currentTeam->id, 'title' => 'Meridian route delayed']);
        CrossPlatformInsight::factory()->create(['team_id' => $user->currentTeam->id, 'title' => 'Unrelated insight']);

        $component = Livewire::actingAs($user)->test(SearchPanel::class)->set('query', 'Meridian');

        $this->assertCount(1, $component->get('nodes'));
        $this->assertCount(1, $component->get('alerts'));
        $this->assertCount(0, $component->get('insights'));
    }

    public function test_results_are_capped_at_ten_per_type(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        Recommendation::factory()->count(15)->create(['team_id' => $user->currentTeam->id, 'title' => 'Reduce idle time on fleet']);

        $component = Livewire::actingAs($user)->test(SearchPanel::class)->set('query', 'idle time');

        $this->assertCount(10, $component->get('recommendations'));
    }

    public function test_dashboard_results_exclude_another_members_private_dashboard(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $member = User::factory()->create();
        $owner->currentTeam->users()->attach($member, ['role' => 'editor']);
        $member->switchTeam($owner->currentTeam);

        AnalyticsDashboard::factory()->create([
            'team_id' => $owner->currentTeam->id,
            'user_id' => $owner->id,
            'title' => 'Ops Private View',
            'visibility' => 'private',
        ]);
        AnalyticsDashboard::factory()->create([
            'team_id' => $owner->currentTeam->id,
            'user_id' => $owner->id,
            'title' => 'Ops Shared View',
            'visibility' => 'team',
        ]);

        $component = Livewire::actingAs($member)->test(SearchPanel::class)->set('query', 'Ops');

        $titles = $component->get('dashboards')->pluck('title');
        $this->assertFalse($titles->contains('Ops Private View'));
        $this->assertTrue($titles->contains('Ops Shared View'));
    }

    public function test_mount_accepts_an_initial_query_from_the_route(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        IntelligenceNode::factory()->create(['team_id' => $user->currentTeam->id, 'label' => 'Preloaded Match']);

        $component = Livewire::actingAs($user)->test(SearchPanel::class, ['query' => 'Preloaded']);

        $this->assertCount(1, $component->get('nodes'));
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Livewire/SearchPanelTest.php`
Expected: FAIL — `App\Livewire\Analytics\SearchPanel` doesn't exist yet.

- [ ] **Step 3: Write `SearchPanel`**

Create `app/Livewire/Analytics/SearchPanel.php`:

```php
<?php

namespace App\Livewire\Analytics;

use App\Models\AnalyticsAlert;
use App\Models\AnalyticsDashboard;
use App\Models\AnalyticsReport;
use App\Models\CrossPlatformInsight;
use App\Models\IntelligenceNode;
use App\Models\Recommendation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Search Panel
 *
 * Finds real content across the six entity types this app has: knowledge
 * graph entities, cross-platform insights, alerts, recommendations, saved
 * reports, and custom dashboards. Uses Scout's database engine (plain LIKE
 * against each model's own table) -- see config/scout.php.
 *
 * Team isolation is automatic for five of the six types via each model's
 * own HasTeamScope global scope. AnalyticsDashboard additionally needs the
 * private/team visibility rule from AnalyticsDashboardPolicy applied here,
 * in PHP, after the Scout query returns -- Scout's own fluent query builder
 * has no closure-based OR condition to express "mine, or shared with the
 * team" directly.
 */
class SearchPanel extends Component
{
    public string $query = '';

    public function mount(string $query = ''): void
    {
        $this->query = $query;
    }

    private function hasQuery(): bool
    {
        return mb_strlen(trim($this->query)) >= 2;
    }

    #[Computed]
    public function nodes(): Collection
    {
        return $this->hasQuery() ? IntelligenceNode::search($this->query)->take(10)->get() : collect();
    }

    #[Computed]
    public function insights(): Collection
    {
        return $this->hasQuery() ? CrossPlatformInsight::search($this->query)->take(10)->get() : collect();
    }

    #[Computed]
    public function alerts(): Collection
    {
        return $this->hasQuery() ? AnalyticsAlert::search($this->query)->take(10)->get() : collect();
    }

    #[Computed]
    public function recommendations(): Collection
    {
        return $this->hasQuery() ? Recommendation::search($this->query)->take(10)->get() : collect();
    }

    #[Computed]
    public function reports(): Collection
    {
        return $this->hasQuery() ? AnalyticsReport::search($this->query)->take(10)->get() : collect();
    }

    // Deliberately no ->take(10) on the Scout query itself here, unlike the
    // five methods above: filtering happens after fetch, so capping the
    // raw query first could drop visible dashboards if several of the top
    // raw matches turn out to be other members' private ones. Row counts
    // per team are small enough that fetching all matches first is fine.
    #[Computed]
    public function dashboards(): Collection
    {
        if (! $this->hasQuery()) {
            return collect();
        }

        return AnalyticsDashboard::search($this->query)->get()
            ->filter(fn (AnalyticsDashboard $d) => $d->user_id === Auth::id() || $d->visibility === 'team')
            ->take(10);
    }

    #[Computed]
    public function totalCount(): int
    {
        return $this->nodes->count() + $this->insights->count() + $this->alerts->count()
            + $this->recommendations->count() + $this->reports->count() + $this->dashboards->count();
    }

    public function render(): View
    {
        return view('livewire.analytics.search-panel');
    }
}
```

`totalCount()` is new, not in the spec's own code sketch — added while
implementing to back the view's "No results for '...'" empty state (Task
4) without every one of the six `@if($this->X->isEmpty())` checks needing
to be repeated in the Blade view's own top-level empty-state condition.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Livewire/SearchPanelTest.php`
Expected: PASS, 5 tests, 0 failures.

- [ ] **Step 5: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `passed`.

- [ ] **Step 6: Commit**

```bash
git add app/Livewire/Analytics/SearchPanel.php tests/Feature/Livewire/SearchPanelTest.php
git commit -m "$(cat <<'EOF'
feat: SearchPanel -- six computed properties, one per entity type

Mirrors DashboardBuilderPanel's own shape (independent computed
properties, not a shared SearchService abstraction). Queries under 2
characters return nothing from every type. AnalyticsDashboard results
are filtered in PHP after the Scout query to enforce the same
private/team visibility rule AnalyticsDashboardPolicy already encodes.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 4: Route, page, and nav-bar entry point

**Files:**
- Modify: `routes/web.php`
- Create: `resources/views/search.blade.php`, `resources/views/livewire/analytics/search-panel.blade.php`
- Modify: `resources/views/navigation-menu.blade.php`
- Test: `tests/Feature/SearchPageTest.php`

**Interfaces:**
- Consumes: `SearchPanel` (Task 3).
- Produces: `search` named route — the feature is reachable from here on.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/SearchPageTest.php` (mirrors `DashboardsPageTest`
exactly):

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_team_member_can_view_the_search_page(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user)
            ->get(route('search'))
            ->assertOk();
    }

    public function test_a_query_string_reaches_the_component(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        \App\Models\IntelligenceNode::factory()->create(['team_id' => $user->currentTeam->id, 'label' => 'Findable Corp']);

        $this->actingAs($user)
            ->get(route('search', ['q' => 'Findable']))
            ->assertOk()
            ->assertSee('Findable Corp');
    }

    public function test_a_user_with_no_current_team_is_redirected_to_team_creation(): void
    {
        $user = User::factory()->create(['current_team_id' => null]);

        $this->actingAs($user)
            ->get(route('search'))
            ->assertRedirect(route('teams.create'));
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get(route('search'))
            ->assertRedirect(route('login'));
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact tests/Feature/SearchPageTest.php`
Expected: FAIL — `Route [search] not defined`.

- [ ] **Step 3: Add the route**

In `routes/web.php`, immediately after the `/dashboards` route added in the
previous PR:

```php
    Route::get('/search', function () {
        if (! Auth::user()->currentTeam) {
            return redirect()->route('teams.create');
        }

        return view('search');
    })->name('search');
```

- [ ] **Step 4: Create the page view**

Create `resources/views/search.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <p class="font-mono" style="font-size:0.7rem;letter-spacing:0.18em;text-transform:uppercase;color:var(--teal-soft);margin:0 0 0.6rem;">Search</p>
        <h1 class="font-display" style="font-size:1.75rem;font-weight:700;color:var(--paper);letter-spacing:-0.01em;margin:0;">Search Results</h1>
    </x-slot>

    <div style="padding:2rem 2.5rem 4rem;">
        <div style="max-width:1180px;margin:0 auto;border:1px solid var(--line);border-radius:14px;overflow:hidden;background:var(--ink-soft);">
            <livewire:analytics.search-panel :query="request('q', '')" />
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 5: Create the results view**

Create `resources/views/livewire/analytics/search-panel.blade.php`:

```blade
<div style="padding:1.75rem 2rem 2rem;">
    <input
        wire:model.live.debounce.300ms="query"
        type="text"
        placeholder="Search insights, alerts, recommendations, reports, dashboards, and the knowledge graph…"
        class="w-full rounded-lg px-4 py-3 text-sm focus:outline-none"
        style="background:var(--ink-soft);border:1px solid var(--line);color:var(--paper);margin-bottom:1.75rem;"
    />

    @if(mb_strlen(trim($query)) < 2)
        <p style="font-size:0.85rem;color:var(--mist);text-align:center;padding:2rem 0;">Type at least 2 characters to search.</p>
    @elseif($this->totalCount === 0)
        <p style="font-size:0.85rem;color:var(--mist);text-align:center;padding:2rem 0;">No results for &ldquo;{{ $query }}&rdquo;.</p>
    @else
        <div style="display:flex;flex-direction:column;gap:1.75rem;">
            @if($this->nodes->isNotEmpty())
                <div>
                    <p class="font-mono" style="font-size:0.65rem;letter-spacing:0.14em;text-transform:uppercase;color:var(--teal-soft);margin:0 0 0.75rem;">Knowledge Graph ({{ $this->nodes->count() }})</p>
                    <div style="display:flex;flex-direction:column;gap:0.5rem;">
                        @foreach($this->nodes as $node)
                            <a href="{{ route('dashboard') }}" style="display:block;padding:0.75rem 1rem;border:1px solid var(--line);border-radius:0.5rem;text-decoration:none;">
                                <div class="flex items-center gap-2">
                                    <span style="font-size:0.85rem;font-weight:600;color:var(--paper);">{{ $node->label }}</span>
                                    <span style="font-size:0.65rem;color:var(--mist);border:1px solid var(--line);border-radius:0.25rem;padding:0.05rem 0.4rem;">{{ $node->entity_type }}</span>
                                </div>
                                <p style="font-size:0.72rem;color:var(--mist);margin:0.2rem 0 0;">{{ $node->entity_id }}</p>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($this->insights->isNotEmpty())
                <div>
                    <p class="font-mono" style="font-size:0.65rem;letter-spacing:0.14em;text-transform:uppercase;color:var(--teal-soft);margin:0 0 0.75rem;">Cross-Platform Insights ({{ $this->insights->count() }})</p>
                    <div style="display:flex;flex-direction:column;gap:0.5rem;">
                        @foreach($this->insights as $insight)
                            <a href="{{ route('dashboard') }}" style="display:block;padding:0.75rem 1rem;border:1px solid var(--line);border-radius:0.5rem;text-decoration:none;">
                                <p style="font-size:0.85rem;font-weight:600;color:var(--paper);margin:0;">{{ $insight->title }}</p>
                                <p style="font-size:0.72rem;color:var(--mist);margin:0.2rem 0 0;">{{ \Illuminate\Support\Str::limit($insight->narrative, 140) }}</p>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($this->alerts->isNotEmpty())
                <div>
                    <p class="font-mono" style="font-size:0.65rem;letter-spacing:0.14em;text-transform:uppercase;color:var(--danger-soft);margin:0 0 0.75rem;">Alerts ({{ $this->alerts->count() }})</p>
                    <div style="display:flex;flex-direction:column;gap:0.5rem;">
                        @foreach($this->alerts as $alert)
                            <a href="{{ route('dashboard') }}" style="display:block;padding:0.75rem 1rem;border:1px solid var(--line);border-radius:0.5rem;text-decoration:none;">
                                <p style="font-size:0.85rem;font-weight:600;color:var(--paper);margin:0;">{{ $alert->title }}</p>
                                <p style="font-size:0.72rem;color:var(--mist);margin:0.2rem 0 0;">{{ \Illuminate\Support\Str::limit($alert->description, 140) }}</p>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($this->recommendations->isNotEmpty())
                <div>
                    <p class="font-mono" style="font-size:0.65rem;letter-spacing:0.14em;text-transform:uppercase;color:var(--gold-soft);margin:0 0 0.75rem;">Recommendations ({{ $this->recommendations->count() }})</p>
                    <div style="display:flex;flex-direction:column;gap:0.5rem;">
                        @foreach($this->recommendations as $rec)
                            <a href="{{ route('dashboard') }}" style="display:block;padding:0.75rem 1rem;border:1px solid var(--line);border-radius:0.5rem;text-decoration:none;">
                                <p style="font-size:0.85rem;font-weight:600;color:var(--paper);margin:0;">{{ $rec->title }}</p>
                                <p style="font-size:0.72rem;color:var(--mist);margin:0.2rem 0 0;">{{ \Illuminate\Support\Str::limit($rec->rationale, 140) }}</p>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($this->reports->isNotEmpty())
                <div>
                    <p class="font-mono" style="font-size:0.65rem;letter-spacing:0.14em;text-transform:uppercase;color:var(--mist);margin:0 0 0.75rem;">Saved Reports ({{ $this->reports->count() }})</p>
                    <div style="display:flex;flex-direction:column;gap:0.5rem;">
                        @foreach($this->reports as $report)
                            <a href="{{ route('dashboard') }}" style="display:block;padding:0.75rem 1rem;border:1px solid var(--line);border-radius:0.5rem;text-decoration:none;">
                                <p style="font-size:0.85rem;font-weight:600;color:var(--paper);margin:0;">{{ $report->title }}</p>
                                @if($report->description)
                                    <p style="font-size:0.72rem;color:var(--mist);margin:0.2rem 0 0;">{{ \Illuminate\Support\Str::limit($report->description, 140) }}</p>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($this->dashboards->isNotEmpty())
                <div>
                    <p class="font-mono" style="font-size:0.65rem;letter-spacing:0.14em;text-transform:uppercase;color:var(--gold-soft);margin:0 0 0.75rem;">Dashboards ({{ $this->dashboards->count() }})</p>
                    <div style="display:flex;flex-direction:column;gap:0.5rem;">
                        @foreach($this->dashboards as $dash)
                            <a href="{{ route('dashboards.index') }}" style="display:block;padding:0.75rem 1rem;border:1px solid var(--line);border-radius:0.5rem;text-decoration:none;">
                                <div class="flex items-center gap-2">
                                    <span style="font-size:0.85rem;font-weight:600;color:var(--paper);">{{ $dash->title }}</span>
                                    @if($dash->visibility === 'team')
                                        <span class="font-mono" style="font-size:0.58rem;background:rgba(43,182,183,0.1);color:var(--teal-soft);padding:0.1rem 0.4rem;border-radius:0.25rem;">shared</span>
                                    @endif
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>
```

Every `<a>` uses inline `style` (not a `.press` class or `<x-nav-link>`)
matching every other in-panel link this app renders, and every color choice
reuses the eyebrow-color convention already established per panel type
(teal for knowledge/insights, danger for alerts, gold for recommendations
and dashboards, mist for reports — no new hue introduced).

- [ ] **Step 6: Add the nav-bar entry point**

In `resources/views/navigation-menu.blade.php`, immediately before the
`<!-- Teams Dropdown -->` comment (inside the same `<div class="hidden
sm:flex sm:items-center sm:ms-6">` the team dropdown lives in):

```blade
                <!-- Search -->
                <form method="GET" action="{{ route('search') }}" class="flex items-center">
                    <input
                        type="text"
                        name="q"
                        placeholder="Search…"
                        value="{{ request('q') }}"
                        style="background:var(--ink-soft);border:1px solid var(--line);color:var(--paper);border-radius:0.4rem;padding:0.4rem 0.75rem;font-size:0.8rem;width:220px;"
                    />
                </form>

```

- [ ] **Step 7: Run the tests to verify they pass**

Run: `php artisan test --compact tests/Feature/SearchPageTest.php`
Expected: PASS, 4 tests, 0 failures.

- [ ] **Step 8: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `passed`.

- [ ] **Step 9: Commit**

```bash
git add routes/web.php resources/views/search.blade.php \
  resources/views/livewire/analytics/search-panel.blade.php \
  resources/views/navigation-menu.blade.php tests/Feature/SearchPageTest.php
git commit -m "$(cat <<'EOF'
feat: make Global Search reachable at /search

New route + page wrapping SearchPanel (finished in the previous three
commits), plus a plain GET search form in the nav bar next to the
team switcher -- no JS needed for the trigger; the results page
itself is Livewire-reactive via wire:model.live.debounce on the query
input. Results link back to /dashboard or /dashboards, since none of
the six searchable entity types has its own page anywhere in this app
(confirmed during spec research).

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 5: Full regression + browser verification

**Files:** none (verification only).

**Interfaces:**
- Consumes: everything from Tasks 1–4.
- Produces: nothing new — confirms the whole feature works together.

- [ ] **Step 1: Run the full test suite**

Run: `php artisan test --compact`
Expected: PASS, 0 failures. Baseline before this plan (end of the Custom
Dashboards PR): 537 passed / 7 skipped. This plan adds 3 + 4 + 5 + 4 = 16
new tests, so expect roughly 553 passed / 7 skipped — as with the previous
plan, this estimate exists to catch a test silently not running, not to
gate on an exact number; 0 failures is what matters.

- [ ] **Step 2: Run Pint across the whole plan's changes**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `passed`.

- [ ] **Step 3: Browser verification**

As an authenticated team member, on the `dot-analytics` dev server:
1. Confirm the search box renders in the nav bar next to the team switcher,
   styled to match.
2. Type a query matching a seeded `MetricDefinition` label or a
   just-created dashboard title, submit, confirm `/search?q=...` loads with
   real results grouped by type.
3. Edit the query directly on the results page (not the nav box) and
   confirm results update live without a full page reload (proves
   `wire:model.live.debounce` is wired correctly).
4. Search something with zero matches — confirm the "No results" empty
   state, not a blank page or error.
5. Search a 1-character query — confirm the "Type at least 2 characters"
   prompt, not an unbounded `LIKE '%x%'` match-everything result.
6. Check the browser console for errors — zero expected.

- [ ] **Step 4: Finish the branch**

Announce: "I'm using the finishing-a-development-branch skill to complete
this work." Follow that skill: verify tests (Step 1 already confirmed
green), detect environment, present the standard menu, execute the chosen
option.

---

## Self-Review Notes

- **Spec coverage:** §1 (wire up Scout) → Task 1 Step 3. §2 (`Searchable` on
  6 models, `AnalyticsDashboard`'s PHP-side filter) → Tasks 1–2 (models),
  Task 3 (the filter itself, in `SearchPanel`). §3 (route + page) → Task 4.
  §4 (`SearchPanel` computed properties) → Task 3. §5 (results layout) →
  Task 4 Step 5. §6 (nav-bar entry point) → Task 4 Step 6. Testing Strategy
  → present throughout, one task per bullet group. Non-Goals (no per-entity
  pages, no external search service, no fuzzy matching, no live nav-box
  preview, no pagination, no command palette, no static-catalog search) —
  no task touches any of those.
- **Placeholder scan:** none found. Every step has real, complete code.
- **Type consistency:** `toSearchableArray()` return shape (plain
  `['field' => $this->field]` arrays of real columns) is identical across
  all 6 models. `SearchPanel::$query` is a plain `string` everywhere it's
  referenced (component property, `mount()` parameter, view binding) — no
  nullable/mixed drift. The `hasQuery()` 2-character threshold is defined
  once and referenced consistently in every computed property and in the
  view's own mirrored `mb_strlen(trim($query)) < 2` check (the view can't
  call the component's private method directly, so it duplicates the same
  literal threshold — flagged here so a future change to the threshold
  remembers to update both places).
