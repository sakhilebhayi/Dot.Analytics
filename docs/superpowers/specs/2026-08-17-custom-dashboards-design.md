# Custom Dashboards — Design Spec

## Context

Following the dashboard redesign (`56002bf`), we brainstormed "reach features" to
make Dot.Analytics more intuitive and useful. Three directions came up: surfacing
the existing-but-unwired dashboard builder, global search, and push notifications
(email/Slack digest + in-app inbox). The user picked the dashboard builder as the
highest-leverage first move, since most of the CRUD scaffolding already exists.

**What a read of the actual code found, before any design started:**

`App\Livewire\Analytics\DashboardBuilderPanel` and its view
(`resources/views/livewire/analytics/dashboard-builder-panel.blade.php`) implement
real, working dashboard/widget CRUD — create/select/delete dashboards, add/remove
widgets — but the feature is incomplete in four concrete ways:

1. **Widgets render nothing.** The widget tile markup
   (`dashboard-builder-panel.blade.php:133-135`) prints only the widget's own
   `widget_type` string as a label. No widget type pulls real data.
2. **Drag-and-drop is broken.** The view calls `new Sortable($el, ...)`
   (`dashboard-builder-panel.blade.php:107`) but no Sortable.js script is loaded
   anywhere in `resources/views/layouts/app.blade.php` — this throws
   `ReferenceError: Sortable is not defined` in the browser today.
3. **Position data is computed but never rendered.** `addWidget()` and
   `updatePositions()` (`DashboardBuilderPanel.php:125-179`) carefully calculate
   `col`/`row` for a 12-column grid, but the view renders a fixed
   `grid-cols-3` (`dashboard-builder-panel.blade.php:104`) that ignores
   `col`/`width`/`height` entirely.
4. **No ownership model.** `dashboards()` (`DashboardBuilderPanel.php:54-60`)
   returns every `AnalyticsDashboard` on the team with no `user_id` filter, and
   `setDefault()` (`DashboardBuilderPanel.php:107-112`) mass-updates
   `is_default` across every dashboard on the team, not just the acting user's
   own. There is no policy — any team member can edit, delete, or re-default
   any other member's dashboard.
5. **No route.** The component and view exist but are wired into no
   `routes/web.php` entry and no navigation link — currently unreachable.
6. **No factories, no tests** exist for `AnalyticsDashboard` or
   `DashboardWidget`.

Also relevant, found while scoping the two data-driven widget types: no
charting library exists anywhere in this codebase (`package.json`, all of
`resources/`) — confirmed by grep. The one other Dot.Analytics chart
(`resources/views/dashboard.blade.php`'s spending-trend precedent lives in
Dot.Finance, not here — checked for ecosystem consistency) and this repo's own
node-graph KPI strip (`dashboard.blade.php:18-30`, from `56002bf`) are both
**hand-built inline SVG**, not a JS charting dependency. This spec follows that
established pattern rather than introducing a new one.

Also: `App\Models\ComputedMetric` (the table that would back a metric/chart
widget) is never written to anywhere in this codebase — no seeder, no command,
no service creates a row. `MetricDefinition` has a seeded 55+ entry catalog
(names/units/descriptions) but the values table behind it is empty in every
environment today. This shapes the Non-Goals below.

## Goal

Turn the dashboard builder into a real, usable feature: personal and
team-shared custom dashboards, reachable from navigation, with widgets that
show real data and a working drag-to-reorder.

## Non-Goals

- **Building a metric-computation pipeline.** This spec visualizes whatever
  `ComputedMetric` rows already exist (currently none in any environment) — it
  does not add a job/command that populates them. The `metric_card` and
  `chart` widgets must degrade to a clear, deliberate empty state when their
  chosen metric has no computed values yet, not a blank or broken tile. Making
  `ComputedMetric` actually get populated is a separate, larger feature.
- **A free-form resizable grid.** The existing `col`/`width`/`height` columns
  on `dashboard_widgets` are not made load-bearing. Widgets stack in a
  responsive one/two-column layout ordered by `row` alone (see Design §4).
  `col`/`width`/`height` stay in the schema, unused, rather than triggering a
  destructive column-drop migration for columns that cost nothing to leave in
  place.
- **A JS charting library.** Per Context above, the `chart` widget is
  hand-built inline SVG, matching this codebase's own established pattern —
  no new CDN dependency, no CSP change needed for this specific piece.
- **Per-viewer "my default" on a dashboard I don't own.** "Default" only ever
  applies to dashboards the acting user owns (see Design §1). A shared
  dashboard someone else created is visible to a team member but is never
  *their* default — building a separate per-viewer preference table for that
  is out of scope.
- **Replacing `/dashboard`.** The existing redesigned dashboard
  (`resources/views/dashboard.blade.php`) is untouched. This ships as a new,
  separate, opt-in `/dashboards` page.
- **Any change to Sortable.js's own drag mechanics** beyond loading it. The
  existing `x-init` wiring in the view (`onEnd` → `$wire.updatePositions`)
  already assumes the library is present; this spec only makes that assumption
  true.

## Design

### 1. Ownership, visibility, and the default flag

**Migration** `2026_08_17_000001_add_visibility_to_analytics_dashboards_table.php`:

```php
Schema::table('analytics_dashboards', function (Blueprint $table) {
    $table->string('visibility')->default('private')->after('is_default');
});
```

No enum type (this codebase doesn't use Postgres enums elsewhere for this kind
of field — `DataSource.status`, `Recommendation.status` etc. are all plain
`string` columns validated at the application layer) — validated in
`DashboardBuilderPanel`'s rules as `in:private,team`.

`AnalyticsDashboard` (`app/Models/AnalyticsDashboard.php`) already has
`HasTeamScope`, so every query is already scoped to the current team. On top
of that:

- `dashboards()` (`DashboardBuilderPanel.php:54-60`) changes to: dashboards
  where `user_id = Auth::id()` **or** `visibility = 'team'`.
- `createDashboard()` always sets `visibility` from a new `$visibility`
  public property (default `'private'`), defaulting the *first dashboard this
  user owns* to `is_default = true` (changed from "first dashboard on the
  team" — `AnalyticsDashboard::where('user_id', Auth::id())->doesntExist()`).
- `setDefault($id)` changes from `AnalyticsDashboard::query()->update(...)`
  (team-wide) to `AnalyticsDashboard::where('user_id', $dashboard->user_id)
  ->update(['is_default' => false])` — only unsets default among dashboards
  owned by *that dashboard's owner*, then sets the target true. Gated by the
  same policy check as other mutations (§2) — a non-owner, non-admin member
  can't call this on someone else's dashboard at all.

### 2. Authorization — `AnalyticsDashboardPolicy`

New `app/Policies/AnalyticsDashboardPolicy.php`, mirroring
`DataSourcePolicy`'s existing shape exactly (same
`isTeamOwnerOrAdmin(User $user, int $teamId): bool` private helper, duplicated
per this codebase's established per-policy convention — no shared trait exists
for it yet, so this doesn't introduce a new pattern):

```php
<?php

namespace App\Policies;

use App\Models\AnalyticsDashboard;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AnalyticsDashboardPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->currentTeam !== null;
    }

    public function view(User $user, AnalyticsDashboard $dashboard): bool
    {
        if ($user->currentTeam?->id !== $dashboard->team_id) {
            return false;
        }

        return $dashboard->user_id === $user->id || $dashboard->visibility === 'team';
    }

    public function create(User $user): bool
    {
        return $user->currentTeam !== null;
    }

    public function update(User $user, AnalyticsDashboard $dashboard): bool
    {
        if ($dashboard->user_id === $user->id) {
            return true;
        }

        return $dashboard->visibility === 'team'
            && $this->isTeamOwnerOrAdmin($user, $dashboard->team_id);
    }

    public function delete(User $user, AnalyticsDashboard $dashboard): bool
    {
        return $this->update($user, $dashboard);
    }

    private function isTeamOwnerOrAdmin(User $user, int $teamId): bool
    {
        $team = $user->currentTeam;
        if (! $team || $team->id !== $teamId) {
            return false;
        }

        return $team->user_id === $user->id
            || $team->users()->where('user_id', $user->id)->wherePivot('role', 'admin')->exists();
    }
}
```

Laravel's naming-convention auto-discovery (`AnalyticsDashboard` →
`AnalyticsDashboardPolicy`) registers this with no manual binding needed —
confirmed by grep: no `$policies` array or `Gate::policy()` call exists in
`AppServiceProvider` for `DataSourcePolicy` or `CrossPlatformInsightPolicy`
either, so this repo already relies on auto-discovery.

`DashboardBuilderPanel`'s mutating methods each start with an authorization
call before touching data:

```php
public function selectDashboard(int $id): void
{
    $dashboard = AnalyticsDashboard::findOrFail($id);
    Gate::authorize('view', $dashboard);
    $this->activeDashboardId = $id;
    // ...
}

public function setDefault(int $id): void { Gate::authorize('update', ...); /* ... */ }
public function deleteDashboard(int $id): void { Gate::authorize('delete', ...); /* ... */ }
public function addWidget(): void { Gate::authorize('update', $this->activeDashboard); /* ... */ }
public function removeWidget(int $id): void { /* load widget -> authorize('update', $widget->dashboard) */ }
public function updatePositions(array $orderedIds): void { /* authorize('update', $this->activeDashboard) once, before the loop */ }
```

`createDashboard()` gets `Gate::authorize('create', AnalyticsDashboard::class)`
(class-level check, matching how `create` abilities are called elsewhere in
this codebase's `@can`/`Gate::authorize` usage).

### 3. Widget types and rendering

Seven widget types, unchanged from `DashboardBuilderPanel::WIDGET_TYPES`. Five
embed an existing, already-restyled Livewire panel directly — real data, no
new rendering code:

| `widget_type` | Renders |
|---|---|
| `alert_feed` | `<livewire:analytics.alerts-panel :key="'w'.$widget->id" />` |
| `recommendation_feed` | `<livewire:analytics.recommendations-panel :key="..." />` |
| `insight_feed` | `<livewire:analytics.cross-platform-insight-panel :key="..." />` |
| `dna_snapshot` | `<livewire:analytics.business-dna-panel :key="..." />` |
| `briefing_summary` | `<livewire:analytics.executive-briefing-panel :key="..." />` |

The `:key` (Livewire's, not the `data-widget-id` used by Sortable) has to be
unique per widget instance since a dashboard could in principle hold more than
one of the same feed type.

The remaining two are new, since nothing like them exists yet:

- **`metric_card`**: config `{'metric_definition_id': int}`. New Blade
  partial `resources/views/livewire/analytics/widgets/metric-card.blade.php`
  — looks up the `MetricDefinition`, finds the most recent `ComputedMetric`
  for the current team (`->latest('period_date')->first()`), shows
  `{{ $metric->label }}` + the value formatted with `{{ $definition->unit }}`.
  Empty state (no `ComputedMetric` row exists — the common case per Context):
  "No computed value yet for {{ $definition->label }}."
- **`chart`**: config `{'metric_definition_id': int}`. New partial
  `resources/views/livewire/analytics/widgets/metric-chart.blade.php` — pulls
  up to the last 12 `ComputedMetric` rows for that definition+team ordered by
  `period_date`, renders a hand-built inline SVG line (viewBox, one `<polyline
  points="...">`, gold line matching the brand's `--gold` token, teal dots at
  each point — same visual grammar as `dashboard.blade.php`'s KPI strip).
  Empty state (0 or 1 data points — can't draw a line): same "No computed
  history yet" message as `metric_card`, not a broken/empty SVG.

**Add-widget form change**
(`dashboard-builder-panel.blade.php`'s `@if($showAddWidget)` block): when
`widgetType` is `metric_card` or `chart`, a new `<select
wire:model="widgetMetricDefinitionId">` appears, populated from
`MetricDefinition::orderBy('label')->get()`. Validated
`required_if:widgetType,metric_card,chart|exists:metric_definitions,id`.
Stored into the widget's `config` column as `['metric_definition_id' => ...]`
in `addWidget()`.

**Widget tile chrome**: each tile in the grid gets a slim header bar (widget
type icon + a "✕ Remove" control) wrapping the embedded content, so a full
panel's own internal header doesn't visually compete with the remove control.
The content area below that header is capped (`max-height: 420px;
overflow-y: auto;`) — the five reused panels (§3) can run tall (e.g. a full
insight or alert list), and an unbounded tile would make a dashboard with a
few widgets scroll for pages. The two new widget types (`metric_card`,
`chart`) are short enough that the cap never engages for them in practice.
Matches the ink/teal/gold/hairline system from `56002bf` throughout — this is
new UI, built once, not restyling something pre-existing.

### 4. Layout and drag-and-drop

The widget grid becomes a responsive stack: `grid grid-cols-1 lg:grid-cols-2
gap-4` (Tailwind), ordered strictly by `row` ascending (`col`/`width`/`height`
read but not used — see Non-Goals). `updatePositions()`'s existing math
(`row = floor(position / 3)`, computed from the dragged DOM order) still
produces a valid strict ordering even though the render no longer treats `row`
as a literal grid row index — only its ordering matters now, which the
existing math already preserves correctly.

Sortable.js is added to `resources/views/layouts/app.blade.php` next to the
existing Alpine CDN script tag:

```html
<script defer src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
```

and to `SecurityHeaders`'s CSP `script-src`, alongside the existing
`cdn.tailwindcss.com`/`unpkg.com` entries added in `54e38c8`:
`https://cdn.jsdelivr.net`.

### 5. Navigation and routing

`routes/web.php`, inside the same `auth:sanctum` + `jetstream.auth_session` +
`verified` group as `/dashboard`:

```php
Route::get('/dashboards', function () {
    if (! Auth::user()->currentTeam) {
        return redirect()->route('teams.create');
    }
    return view('dashboards');
})->name('dashboards.index');
```

New `resources/views/dashboards.blade.php`: `<x-app-layout>` wrapping
`<livewire:analytics.dashboard-builder-panel />`, same hairline-container
treatment as `dashboard.blade.php` (`56002bf`) for visual consistency.

`resources/views/layouts/app.blade.php`'s sidebar `<nav>` gets a second `.sl`
link below "Intelligence Overview":

```blade
<a href="{{ route('dashboards.index') }}" class="sl {{ request()->routeIs('dashboards.index') ? 'active' : '' }}">
    <span class="material-symbols-outlined" style="font-size:18px;">dashboard_customize</span>
    <span>My Dashboards</span>
</a>
```

## Testing Strategy

- **`AnalyticsDashboardPolicyTest`** (new, mirrors `GateTest.php`'s
  per-ability convention): owner can view/update/delete their own private
  dashboard; a different team member cannot view another member's private
  dashboard; any team member can view a `visibility=team` dashboard; only the
  owner or a team admin can update/delete a `visibility=team` dashboard — a
  regular member cannot; a user from a different team cannot view any of it
  regardless of visibility (team-scope still enforced by `HasTeamScope`).
- **`DashboardBuilderPanelTest`** (new Livewire test, no existing test file
  for this component): creating a dashboard sets `user_id`/`team_id` and
  `is_default` correctly (first *owned* dashboard becomes default, not first
  team-wide); adding each of the 7 widget types persists the right `config`;
  `metric_card`/`chart` reject a missing `metric_definition_id`; removing a
  widget is denied (`AuthorizationException`) for a non-owner, non-admin
  member on someone else's private dashboard; `updatePositions` persists a new
  `row` ordering; `setDefault` only ever unsets `is_default` on dashboards
  owned by that dashboard's owner, not team-wide.
- **Widget rendering smoke tests**: `metric_card` with a seeded
  `ComputedMetric` shows the formatted value and unit; `metric_card` with none
  shows the empty-state message, not a blank tile or error; `chart` with 0/1
  points shows the same empty state instead of attempting to draw a line;
  `chart` with several points renders valid, well-formed SVG.
- **New factories**: `AnalyticsDashboardFactory`, `DashboardWidgetFactory` —
  neither exists yet, needed by every test above.
- Full existing suite (currently 504 passing / 7 skipped) stays green.
- `vendor/bin/pint --dirty --format agent` on every PHP file touched.

## Out of Scope

- A metric-computation pipeline that populates `ComputedMetric` (Non-Goals).
- Resizable/free-form grid positioning (Non-Goals).
- A JS charting dependency (Non-Goals).
- Global search and push notifications/digests — the other two brainstormed
  directions from this session, deferred, not started here.
- Mobile-responsive sidebar — a pre-existing, separately-noted gap, not
  touched by this feature.
