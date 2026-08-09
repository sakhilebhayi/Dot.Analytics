# Recommendation Authorization Gate — Design Spec

## Context

This spec is part of the ecosystem-wide Autonomy & Owner-Independence Program (per
[brain.autonomy.md](https://github.com/sakhilebhayi/Dot.Brain/blob/main/brain.autonomy.md) §2),
applied here to Dot.Analytics.

**The platform audit was checked against real code and found to be wrong on its
one Level 2 candidate.** [`Dot.Brain/platforms/dot-analytics.md`](https://github.com/sakhilebhayi/Dot.Brain/blob/main/platforms/dot-analytics.md)
§7 describes a "KPI-catalog sync" job — a daily `analytics.catalog.synced` event
diffing a KPI catalog against a registry and auto-blocking publication on drift.
The audit's own text admits this "is not something re-verified line-by-line
against a scheduler entry in this audit." It should have been verified: grepping
the real repo for `Kpi`, `catalog`, and `CatalogSync` across `app/` returns
nothing. No `Kpi` model, no `analytics:catalog-sync` command, no such event
exists anywhere in the codebase. The audit's suggested Level 2 candidate
(rewire that job into an approval gate) cannot be built — there is nothing to
rewire.

A thorough survey of every real scheduled job (`routes/console.php`), queued
job (`app/Jobs/Analytics/`), and side-effecting service (`app/Services/`)
found no genuine Level 2 gap and no Level 3-executing-as-Level-1 violation
(no `prune`/`delete`/`forceDelete`/`MassPrunable` anywhere in `app/Console`,
`app/Jobs`, or the scheduler). Every real automated pipeline
(`RunIntelligenceEnginesCommand`, `GenerateBriefingsCommand`,
`RecomputeDnaCommand`, `IngestPlatformSnapshotJob`, `AnomalyDetectionService`)
is in-tenant, non-destructive, report/compute-only — genuinely Level 1, no
gap to close.

**The real, separate defect found instead:** `App\Livewire\Analytics\RecommendationsPanel::action()`
and `::dismiss()` (`app/Livewire/Analytics/RecommendationsPanel.php:75-84`) have
**no authorization check at all** — unlike every other mutating method in this
codebase. `Gate::authorize('manage-platforms')` (or an equivalent named gate)
is the first line of every comparable mutator: `FeatureFlagsPanel::create()`/`toggle()`/`setRollout()`
(`app/Livewire/Analytics/FeatureFlagsPanel.php:40,56,70`),
`DataSourcePolicy::update()`/`delete()`, `CrossPlatformInsightPolicy::delete()`.
`RecommendationsPanel` alone skips it: any authenticated user, regardless of
role, can call `action($id)` or `dismiss($id)` on any pending recommendation
belonging to their own team, silently changing its status with no role check
at all.

**Correction made during implementation:** this spec originally also
described the gap as cross-tenant (any team could touch any other team's
recommendation by ID). That part was wrong — `Recommendation` uses the
`HasTeamScope` trait (`app/Models/Concerns/HasTeamScope.php`), which applies
a global query scope keyed on `Auth::user()->currentTeam->id`. This was
missed during the design phase (the model file was read for its `$fillable`/
relations but the trait's effect wasn't traced) and only surfaced once the
cross-tenant test was actually run: `Recommendation::findOrFail($id)` already
throws `ModelNotFoundException` for another team's id, before this fix and
after it. The real, narrower gap is exactly what's described above — same-team,
wrong-role access — and that's what this fix closes.

## Goal

Gate `RecommendationsPanel::action()` and `::dismiss()` behind the same
team-owner-or-admin check every other mutating action in this codebase already
uses, and scope the lookup to the acting user's own team so a cross-tenant `$id`
can't be actioned at all.

## Non-Goals

- No new role/permission concept. Jetstream's team `owner` (`Team::user_id`)
  and `admin` pivot role already exist and already gate every comparable
  action (`AppServiceProvider::isTeamOwnerOrAdmin()`) — this reuses that
  exactly, per this program's established preference for reusing a real
  existing role concept over inventing a new flag.
- No change to `Recommendation`'s `pending → actioned/dismissed` state
  machine, its AI-generation flow (`generate()`), or its schema.
- No change to the blade view's button visibility. `FeatureFlagsPanel`'s
  buttons stay visible to every viewer too; the backend `Gate::authorize()`
  call is the real boundary in this codebase's existing convention, not
  conditional rendering.
- Not building a Level 2 approval-gate workflow (proposal → review → execute).
  `action()`/`dismiss()` already only flip a status field a human sets after
  doing the real thing themselves elsewhere (per `action_url`/`action_label`)
  — this is authorization hardening on an existing feature, not a new
  approval-gate pipeline.

## Design

### 1. New named Gate

`app/Providers/AppServiceProvider.php`, inside the existing `boot()` gate
block (alongside `manage-platforms`, `run-intelligence-engines`, etc.):

```php
Gate::define('manage-recommendations', fn ($user) => $this->isTeamOwnerOrAdmin($user));
```

Reuses the existing private `isTeamOwnerOrAdmin(User $user): bool` helper
already defined in this file (checks `$user->currentTeam`; true if
`$team->user_id === $user->id` or the user has the `admin` pivot role on
that team; false if no current team) — no new logic.

### 2. Gate the two mutating methods

`app/Livewire/Analytics/RecommendationsPanel.php`:

```php
public function action(int $id): void
{
    Gate::authorize('manage-recommendations');

    Recommendation::findOrFail($id)->update(['status' => 'actioned']);
    unset($this->recommendations);
}

public function dismiss(int $id): void
{
    Gate::authorize('manage-recommendations');

    Recommendation::findOrFail($id)->update(['status' => 'dismissed']);
    unset($this->recommendations);
}
```

No explicit `->where('team_id', ...)` is needed — `Recommendation`'s own
`HasTeamScope` trait already applies that as a global scope on every query
(see the Context section's correction above), so `findOrFail($id)` already
only ever resolves a row belonging to the acting user's current team.
Explicitly repeating that condition here would be redundant with, not
additive to, the trait's own stated design goal (its docblock: "a forgotten
`where('team_id', ...)` call... can no longer leak another team's rows,
because the model itself never returns unscoped results").

`Gate::authorize()` throws `Illuminate\Auth\Access\AuthorizationException` on
failure, which Livewire's test harness converts to an assertable 403 —
matching `FeatureFlagsPanel`'s exact convention and confirmed against this
codebase's own `RemoveTeamMemberTest::test_only_team_owner_can_remove_team_members`.
A cross-tenant `$id`, by contrast, throws `ModelNotFoundException` from the
trait's global scope — confirmed this is **not** converted to an assertable
HTTP response by Livewire's test `call()` the way `AuthorizationException`
is; it must be asserted as a raw thrown exception in tests (see the plan's
Task 1 for the resulting test shape).

`Auth` is already imported in this file (used by `generate()`) but is no
longer needed by these two methods after removing the explicit scope.
`Illuminate\Support\Facades\Gate` is **not** currently imported (confirmed —
the file's import block has no `Gate` line) and must be added.

### 3. Tests

**`tests/Feature/Authorization/GateTest.php`** — extend the existing
gate-by-gate convention with a `manage-recommendations` section, mirroring
`manage-platforms`'s two tests exactly (owner allowed, no-team denied).

**New `tests/Feature/Livewire/RecommendationsPanelAuthorizationTest.php`**
(new file, since no `RecommendationsPanel` Livewire test exists yet):
- A team owner can `action()` a pending recommendation belonging to their
  team; status becomes `actioned`.
- A team admin (attached via `$team->users()->attach($user, ['role' => 'admin'])`,
  matching `RemoveTeamMemberTest`'s established fixture convention) can
  `dismiss()` a pending recommendation; status becomes `dismissed`.
- A non-admin team member (attached with a role other than `admin`) calling
  `action()` gets a 403; the recommendation's status is unchanged.
- A different team's owner calling `action()` on this team's recommendation
  id gets a `ModelNotFoundException` (Livewire surfaces this as a 404); the
  recommendation's status is unchanged.

## Testing Strategy

Feature tests only (Livewire component tests + the existing `GateTest.php`
convention) — this codebase has no unit-level policy test file for
`isTeamOwnerOrAdmin()` itself (it's private, tested only through the gates it
backs), so the new gate follows that same testing shape rather than
introducing a new one.

## Out of Scope

- Rebuilding or verifying §7's fictional KPI-catalog sync — not real, nothing
  to fix.
- Any change to `Dot.Brain/platforms/dot-analytics.md` itself (Dot.Brain owns
  that file; this program's platform-repo work doesn't edit Brain docs).
- Hiding the Act/Dismiss buttons from non-admins in the blade view (see
  Non-Goals).
- Any other Livewire component's authorization (only `RecommendationsPanel`
  was found missing a gate in this audit's scope).
