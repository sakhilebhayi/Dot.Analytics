# Global Search — Design Spec

## Context

Following Custom Dashboards, we brainstormed the two remaining reach-feature
directions from the original session (global search, push notifications).
The user asked for an explanation of global search before committing to
build it; that discussion (grounded in the real codebase, not a generic
"add search" pitch) narrowed the scope through two decisions:

- **Scope**: a dedicated `/search` page (not a Cmd+K command-palette overlay,
  not a surgical fix scoped to only the Knowledge Graph form).
- **Entry point**: a search box in the top nav bar, next to the team
  switcher, submitting via a plain GET form to `/search?q=...`.

**What a read of the actual code found, before any design started:**

`laravel/scout` (`v10.25.0`, confirmed via `composer.lock`) is already a
direct dependency — but it is completely unconfigured and unused. No
`config/scout.php` has ever been published, no `SCOUT_DRIVER` is set in
`.env`/`.env.example`, and grepping every model in `app/Models` finds not one
using the `Searchable` trait. Same shape as the dashboard builder before the
previous PR: real infrastructure sitting unused rather than genuinely
missing.

The concrete pain point that motivated this in the first place: the
Knowledge Graph panel's "Traverse Graph" form (`resources/views/livewire/
analytics/knowledge-graph-panel.blade.php`) makes you type an *exact*
`IntelligenceNode.entity_id` into a text box, with no way to look one up —
a real friction point on a platform whose entire pitch is tracing
relationships across the ecosystem.

Six models are realistic search targets, all of them already carrying
`HasTeamScope` (confirmed by reading each one directly, not assumed):
`IntelligenceNode`, `CrossPlatformInsight`, `AnalyticsAlert`,
`Recommendation`, `AnalyticsReport`, `AnalyticsDashboard`. Team isolation is
therefore automatic for five of the six — `AnalyticsDashboard` is the one
exception: `HasTeamScope` alone would let search surface the *titles* of
other team members' **private** dashboards, since visibility (`private`/
`team`) is a second, independent access rule this codebase added in the
Custom Dashboards PR, not something `HasTeamScope`'s single team-wide global
scope knows about.

**A real gap found while designing where search results should link to**:
none of these six entity types has an individual "show" page anywhere in
this app today. An insight, an alert, a recommendation, a saved report, a
graph node — none of them has its own URL. Everything lives inside a panel
on `/dashboard` (or, for dashboards themselves, inside the `/dashboards`
builder's sidebar list, selected via Livewire component state, not a URL).
This shapes the results-page design below: results show enough detail
inline to be useful on their own, rather than linking to pages that don't
exist.

## Goal

A `/search` page, reachable from a nav-bar search box on every authenticated
page, that finds real content — knowledge graph entities, cross-platform
insights, alerts, recommendations, saved reports, and dashboards — by
wiring up the Scout dependency that's already installed, using its
zero-infrastructure `database` engine.

## Non-Goals

- **No per-entity "show" pages.** Per the Context section's finding, none
  exist today for any of these six types; building six of them is a much
  larger, separate effort than search itself. Results are self-contained on
  the search page and link back to the existing page each type already
  lives on (`/dashboard` or `/dashboards`), not to a new page invented for
  this feature.
- **No external search service.** Scout's `database` engine runs plain
  `LIKE` queries against each model's own table — no Meilisearch, Algolia,
  or Typesense, no new infrastructure, no data-sync job to keep an external
  index current.
- **No fuzzy/typo-tolerant matching.** An explicit, known trade-off of
  choosing the zero-infra `database` engine over a real search service —
  "aalerts" will not find "Alerts". Acceptable for v1; revisit only if this
  becomes a real complaint.
- **No live-as-you-type dropdown preview** on the nav-bar search box itself.
  Typing and hitting enter navigates to the full `/search` results page,
  matching the entry-point decision already made. The results page itself
  *is* live (editing the query there re-searches via Livewire without a
  full page reload) — only the nav-bar trigger is a plain form submission.
- **No pagination.** Each entity-type section is capped at 10 results.
  Revisit if real usage shows this cap is actually hit often.
- **No command palette / global keyboard shortcut.** The explicitly-declined
  broader option from the scoping discussion.
- **The static 15-platform connect catalog is not searchable.** It's a
  fixed onboarding list (`IntelligenceEngineService::PLATFORMS`), not team
  content.

## Design

### 1. Wire up Scout

Publish `config/scout.php` (`php artisan vendor:publish --tag=scout-config`)
and set in `.env`/`.env.example`:

```
SCOUT_DRIVER=database
```

No migration, no index-table, no queue worker needed — the `database`
engine (Scout ≥10.8, confirmed present at `v10.25.0`) queries each model's
real table directly via `LIKE`, using whatever `toSearchableArray()`
returns as the set of columns to match against.

### 2. `Searchable` on the six models

Each model adds `use Laravel\Scout\Searchable;` and a `toSearchableArray()`
returning its real, existing columns — no synthetic fields, no new columns:

| Model | `toSearchableArray()` |
|---|---|
| `IntelligenceNode` | `label`, `entity_type`, `entity_id` |
| `CrossPlatformInsight` | `title`, `narrative` |
| `AnalyticsAlert` | `title`, `description` |
| `Recommendation` | `title`, `rationale` |
| `AnalyticsReport` | `title`, `description` |
| `AnalyticsDashboard` | `title` |

Example (`app/Models/AnalyticsAlert.php`):

```php
use Laravel\Scout\Searchable;

class AnalyticsAlert extends Model
{
    use HasFactory, HasTeamScope, Searchable;

    public function toSearchableArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
        ];
    }
    // ...
}
```

Because the `database` engine's search ultimately runs through each model's
own Eloquent query, `HasTeamScope`'s global scope still applies automatically
— `AnalyticsAlert::search($q)->get()` can never return another team's row,
exactly like every other query against these models today.

**The `AnalyticsDashboard` exception**: Scout's fluent query builder
(`Builder::where($field, $value)`) only expresses simple equality `AND`
constraints — it has no closure-based `where(fn ($q) => ...)` the way
Eloquent's own builder does, so "`user_id` = me OR `visibility` = 'team'"
isn't directly expressible through Scout's own builder API. Rather than
fight that API for one model, the dashboard search results are filtered in
PHP after the (already team-scoped) Scout query returns, reusing the exact
rule `AnalyticsDashboardPolicy::view()` already encodes:

```php
AnalyticsDashboard::search($this->query)->get()
    ->filter(fn (AnalyticsDashboard $d) => $d->user_id === Auth::id() || $d->visibility === 'team');
```

Row counts per team are small (a handful of dashboards at most) — filtering
a small collection in PHP after the query is simple and correct, not a
performance concern.

### 3. Route and page

`routes/web.php`, same middleware group and reachable-null pattern as
`/dashboard`/`/dashboards`:

```php
Route::get('/search', function () {
    if (! Auth::user()->currentTeam) {
        return redirect()->route('teams.create');
    }

    return view('search');
})->name('search');
```

`resources/views/search.blade.php`: `<x-app-layout>` wrapping a new
`<livewire:analytics.search-panel :query="request('q', '')" />`, matching
the exact `/dashboards`-established pattern (Blade page + one embedded
Livewire component, not a Livewire-component-as-route-target — this app has
no precedent for that style and this spec doesn't introduce one).

### 4. `SearchPanel` component

New `app/Livewire/Analytics/SearchPanel.php`. One `#[Computed]` property per
entity type (mirroring `DashboardBuilderPanel`'s own shape — several small,
independent computed properties, not a shared "SearchService" abstraction
this codebase doesn't otherwise use), each capped at 10 and skipped entirely
when the query is under 2 characters:

```php
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

    public function render(): View
    {
        return view('livewire.analytics.search-panel');
    }
}
```

`wire:model.live="query"` on the results page's own search input re-runs
every computed property as the user edits — the "live" half of the
Non-Goals distinction (nav-bar trigger is a dumb form; the results page
itself is reactive).

### 5. Results page layout

One section per entity type, only rendered when it has results, each with a
mono-uppercase eyebrow + count (matching every other panel's heading
convention from the dashboard redesign) and up to 10 result rows showing
title/label + a truncated snippet of the secondary field (narrative/
description/rationale) + a link back to the page that type lives on:

- `IntelligenceNode` → label, entity type badge, entity ID, links to
  `/dashboard#knowledge-graph` (the closest thing to a deep link this app
  has — no query-string pre-fill in v1, see Out of Scope).
- `CrossPlatformInsight`/`AnalyticsAlert`/`Recommendation` → title + snippet,
  link to `/dashboard`.
- `AnalyticsReport` → title + description, link to `/dashboard` (saved
  reports panel).
- `AnalyticsDashboard` → title + visibility badge (reusing the "shared" badge
  language from the Custom Dashboards sidebar), link to `/dashboards`.

An empty state ("Type at least 2 characters to search" for a short/blank
query; "No results for '{{ $query }}'" when every section comes back empty)
matches this app's established empty-state voice throughout every panel
built in the previous PR.

### 6. Nav-bar entry point

`resources/views/layouts/app.blade.php`, in the top bar area (next to the
team switcher in `navigation-menu.blade.php`, matching where that control
already lives):

```blade
<form method="GET" action="{{ route('search') }}" class="flex items-center">
    <input type="text" name="q" placeholder="Search…" value="{{ request('q') }}"
        style="background:var(--ink-soft);border:1px solid var(--line);color:var(--paper);border-radius:0.4rem;padding:0.4rem 0.75rem;font-size:0.8rem;" />
</form>
```

Plain GET form — no JS needed, no Livewire component on the trigger itself.
Landing on `/search?q=...` is a normal full page navigation from anywhere in
the app; the results page takes over reactively from there.

## Testing Strategy

- **Model search tests** (one per model, or a shared parameterized-style
  suite): `Model::search($q)->get()` finds a matching row by each searched
  field; a team-scoped search never returns another team's row (reusing the
  same cross-tenant test shape established in `RecommendationsPanelAuthorizationTest`
  and `AnalyticsDashboardPolicyTest`).
- **`AnalyticsDashboard` visibility test**: a search result set never
  includes another team member's private dashboard, but does include a
  `visibility='team'` dashboard the searcher doesn't own — mirrors
  `AnalyticsDashboardPolicyTest`'s own view-boundary tests exactly.
- **`SearchPanel` Livewire tests**: a 1-character query returns nothing from
  every computed property (the `hasQuery()` guard); a real query returns
  matching rows across multiple types simultaneously; results are capped at
  10 per type.
- **`/search` route test**: reachable for a team member, redirects a
  no-team user to `teams.create`, matching `DashboardsPageTest`'s exact
  shape.
- Full existing suite stays green throughout.

## Out of Scope

- Per-entity show pages (Non-Goals).
- An external search service (Non-Goals).
- Query-string pre-fill of the Knowledge Graph traversal form from a node
  search result — the result links to the graph panel, but doesn't yet
  auto-populate `entityType`/`entityId` for you. A natural fast-follow, not
  built here.
- Fuzzy matching, pagination, command palette (Non-Goals).
- Push notifications — the other remaining brainstormed direction, still
  deferred.
