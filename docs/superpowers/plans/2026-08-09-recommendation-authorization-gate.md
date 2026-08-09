# Recommendation Authorization Gate Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close the missing-authorization gap on `RecommendationsPanel::action()`/`::dismiss()` by reusing this codebase's existing team-owner-or-admin gate pattern, and scope the underlying lookup to the acting user's own team.

**Architecture:** One new named Laravel Gate (`manage-recommendations`) defined in `AppServiceProvider::boot()`, reusing the private `isTeamOwnerOrAdmin()` helper already used by five other gates in that file. `RecommendationsPanel`'s two mutating methods call `Gate::authorize('manage-recommendations')` as their first line (matching `FeatureFlagsPanel`'s exact convention) and scope their `Recommendation` lookup to `Auth::user()->currentTeam->id`.

**Tech Stack:** Laravel 13, Livewire, PHPUnit, Jetstream teams (owner via `Team::user_id`, `admin` pivot role via `team_user.role`).

## Global Constraints

- Reuse `AppServiceProvider`'s existing private `isTeamOwnerOrAdmin(User $user): bool` helper verbatim — do not duplicate its logic.
- Gate name: exactly `manage-recommendations`, following the existing kebab-case verb-noun convention (`manage-platforms`, `manage-connectors`).
- `Gate::authorize()` (not `Gate::allows()`/`abort_unless()`) — matches `FeatureFlagsPanel`'s exact convention, so a denial throws `Illuminate\Auth\Access\AuthorizationException` → Livewire surfaces it as a 403.
- Scope the `Recommendation` lookup to `Auth::user()->currentTeam->id` before `findOrFail()`, so a cross-tenant id 404s (`ModelNotFoundException`) rather than succeeding.
- No blade view changes — buttons stay visible to all viewers, matching `FeatureFlagsPanel`'s own convention where the backend gate is the real boundary.
- No new role/permission concept, no migration, no change to `Recommendation`'s schema or `generate()` method.
- Per this repo's own `.github/instructions/laravel-boost.instructions.md`-equivalent rule (`CLAUDE.md`'s Laravel Boost guidelines, "Verification Scripts" section): do not create verification scripts or use `tinker` when tests cover the functionality and prove it works.
- Run `vendor/bin/pint --dirty --format agent` after every task before committing (per this repo's Pint guideline).

---

### Task 1: Gate definition + RecommendationsPanel authorization fix

**Files:**
- Modify: `app/Providers/AppServiceProvider.php` (add one `Gate::define()` call inside the existing gate block in `boot()`)
- Modify: `app/Livewire/Analytics/RecommendationsPanel.php` (add `Gate` import, gate + scope `action()`/`dismiss()`)
- Test: `tests/Feature/Livewire/RecommendationsPanelAuthorizationTest.php` (new file)

**Interfaces:**
- Consumes: `AppServiceProvider::isTeamOwnerOrAdmin(User $user): bool` (existing, private, called only from within `Gate::define()` closures in the same class — no external interface needed).
- Produces: Gate name `manage-recommendations`, resolvable via `Gate::authorize('manage-recommendations')`, `$user->can('manage-recommendations')`, or `Gate::allows('manage-recommendations')` anywhere in the app.

- [ ] **Step 1: Write the failing Livewire authorization tests**

Create `tests/Feature/Livewire/RecommendationsPanelAuthorizationTest.php`:

```php
<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Analytics\RecommendationsPanel;
use App\Models\Recommendation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RecommendationsPanelAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_owner_can_action_a_pending_recommendation(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $recommendation = Recommendation::factory()->create([
            'team_id' => $owner->currentTeam->id,
            'status' => 'pending',
        ]);

        Livewire::actingAs($owner)
            ->test(RecommendationsPanel::class)
            ->call('action', $recommendation->id);

        $this->assertSame('actioned', $recommendation->fresh()->status);
    }

    public function test_team_admin_can_dismiss_a_pending_recommendation(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $admin = User::factory()->create();
        $owner->currentTeam->users()->attach($admin, ['role' => 'admin']);
        $admin->switchTeam($owner->currentTeam);

        $recommendation = Recommendation::factory()->create([
            'team_id' => $owner->currentTeam->id,
            'status' => 'pending',
        ]);

        Livewire::actingAs($admin)
            ->test(RecommendationsPanel::class)
            ->call('dismiss', $recommendation->id);

        $this->assertSame('dismissed', $recommendation->fresh()->status);
    }

    public function test_non_admin_team_member_is_forbidden(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $member = User::factory()->create();
        $owner->currentTeam->users()->attach($member, ['role' => 'editor']);
        $member->switchTeam($owner->currentTeam);

        $recommendation = Recommendation::factory()->create([
            'team_id' => $owner->currentTeam->id,
            'status' => 'pending',
        ]);

        Livewire::actingAs($member)
            ->test(RecommendationsPanel::class)
            ->call('action', $recommendation->id)
            ->assertForbidden();

        $this->assertSame('pending', $recommendation->fresh()->status);
    }

    public function test_a_different_teams_owner_cannot_action_this_teams_recommendation(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $otherOwner = User::factory()->withPersonalTeam()->create();

        $recommendation = Recommendation::factory()->create([
            'team_id' => $owner->currentTeam->id,
            'status' => 'pending',
        ]);

        Livewire::actingAs($otherOwner)
            ->test(RecommendationsPanel::class)
            ->call('action', $recommendation->id)
            ->assertStatus(404);

        $this->assertSame('pending', $recommendation->fresh()->status);
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail as expected**

Run: `php artisan test --compact tests/Feature/Livewire/RecommendationsPanelAuthorizationTest.php`

Before any implementation change, `action()`/`dismiss()` have no gate and no
team scope, so:
- `test_team_owner_can_action_a_pending_recommendation` and
  `test_team_admin_can_dismiss_a_pending_recommendation` already PASS (there
  is nothing yet stopping either from succeeding).
- `test_non_admin_team_member_is_forbidden` FAILS —
  `assertForbidden()` fails because the call currently succeeds (200/OK
  Livewire response, status flips to `actioned`) instead of 403.
- `test_a_different_teams_owner_cannot_action_this_teams_recommendation`
  FAILS — `assertStatus(404)` fails for the same reason (the unscoped
  `findOrFail($id)` finds the other team's row and updates it).

Expected: 2 passed, 2 failed. Confirm the 2 failures are exactly those two
tests before proceeding — if a different test fails, stop and investigate
before implementing.

- [ ] **Step 3: Add the `manage-recommendations` gate**

In `app/Providers/AppServiceProvider.php`, inside `boot()`, immediately after
the existing `Gate::define('view-audit-logs', ...)` block and before the
`Gate::define('view-intelligence', ...)` block:

```php
Gate::define('manage-recommendations', fn ($user) => $this->isTeamOwnerOrAdmin($user)
);
```

- [ ] **Step 4: Gate and scope `RecommendationsPanel`'s mutating methods**

In `app/Livewire/Analytics/RecommendationsPanel.php`, add the import:

```php
use Illuminate\Support\Facades\Gate;
```

Replace the `action()` and `dismiss()` methods:

```php
public function action(int $id): void
{
    Gate::authorize('manage-recommendations');

    Recommendation::where('team_id', Auth::user()->currentTeam->id)
        ->findOrFail($id)
        ->update(['status' => 'actioned']);

    unset($this->recommendations);
}

public function dismiss(int $id): void
{
    Gate::authorize('manage-recommendations');

    Recommendation::where('team_id', Auth::user()->currentTeam->id)
        ->findOrFail($id)
        ->update(['status' => 'dismissed']);

    unset($this->recommendations);
}
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Livewire/RecommendationsPanelAuthorizationTest.php`
Expected: PASS, 4 tests, 0 failures.

- [ ] **Step 6: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: reports `passed` or auto-fixes formatting on the two touched files.

- [ ] **Step 7: Commit**

```bash
git add app/Providers/AppServiceProvider.php \
  app/Livewire/Analytics/RecommendationsPanel.php \
  tests/Feature/Livewire/RecommendationsPanelAuthorizationTest.php
git commit -m "$(cat <<'EOF'
fix: gate RecommendationsPanel action/dismiss behind team owner/admin

action()/dismiss() previously had no authorization check at all --
any authenticated user on any team could flip another team's
recommendation to actioned/dismissed by guessing its id. Every other
mutating method in this codebase (FeatureFlagsPanel, DataSourcePolicy,
CrossPlatformInsightPolicy) already gates on team owner/admin; this
reuses the same isTeamOwnerOrAdmin() gate pattern via a new
manage-recommendations gate, and scopes the lookup to the acting
user's own team so a cross-tenant id 404s instead of succeeding.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 2: Extend GateTest.php coverage + full regression

**Files:**
- Modify: `tests/Feature/Authorization/GateTest.php` (add a `manage-recommendations` section, mirroring the existing `manage-platforms` section)

**Interfaces:**
- Consumes: `manage-recommendations` gate (Task 1).
- Produces: nothing new — this task only adds test coverage and verifies the whole suite.

- [ ] **Step 1: Write the failing gate-level tests**

In `tests/Feature/Authorization/GateTest.php`, add a new section after the
existing `// ─── view-audit-logs ─────` block and before `// ─── view-intelligence ───`:

```php
    // ─── manage-recommendations ──────────────────────────────────────────────

    public function test_manage_recommendations_allows_team_owner(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);

        $this->assertTrue($owner->can('manage-recommendations'));
    }

    public function test_manage_recommendations_denies_user_with_no_team(): void
    {
        $userWithNoTeam = new User;
        $userWithNoTeam->id = 9999;

        $gate = app(Gate::class);

        $result = $gate->forUser($userWithNoTeam)->check('manage-recommendations');
        $this->assertFalse($result);
    }
```

This mirrors `test_manage_platforms_allows_team_owner` /
`test_manage_platforms_denies_user_with_no_team` exactly, substituting the
gate name.

Unlike Task 1, this task adds no new production code — the
`manage-recommendations` gate was already implemented in Task 1. This step is
pure test-coverage addition (extending `GateTest`'s existing one-section-per-gate
convention for consistency), so there is no red step: the new tests are
expected to pass immediately, proving Task 1's gate is wired correctly.

- [ ] **Step 2: Run the new tests and confirm they pass**

Run: `php artisan test --compact tests/Feature/Authorization/GateTest.php --filter=manage_recommendations`
Expected: PASS, 2 tests, 0 failures. If either fails, Task 1's gate
definition is wrong and must be fixed before continuing.

- [ ] **Step 3: Run the full GateTest file**

Run: `php artisan test --compact tests/Feature/Authorization/GateTest.php`
Expected: PASS, all tests in the file including the 2 new ones.

- [ ] **Step 4: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `passed`.

- [ ] **Step 5: Run the full test suite**

Run: `php artisan test --compact`
Expected: 0 failures across the full suite (this repo's baseline before this
plan started, per the earlier `feature/ecosystem-sso` branch state, was 494
tests / 487 passed / 1070 assertions per `Dot.Brain/platforms/dot-analytics.md`'s
"Verified Infrastructure State" note — the exact current count may differ;
what matters is 0 failures, not matching that historical count exactly).

- [ ] **Step 6: Commit**

```bash
git add tests/Feature/Authorization/GateTest.php
git commit -m "$(cat <<'EOF'
test: cover manage-recommendations in the existing GateTest suite

Extends GateTest's existing gate-by-gate convention (owner allowed,
no-team denied) to the new manage-recommendations gate added in the
previous commit, matching the manage-platforms section exactly.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

## Self-Review Notes

- **Spec coverage:** §1 (new gate) → Task 1 Step 3. §2 (gate + scope the two
  methods) → Task 1 Step 4. §3 tests (GateTest extension, new Livewire test
  file) → Task 1 Step 1 (Livewire test) and Task 2 Step 1 (GateTest
  extension). Non-goals (no blade changes, no schema changes, no new role
  concept) — none of the tasks touch those files. Full coverage confirmed.
- **Placeholder scan:** none found after resolving the spec's own
  "confirm at implementation time" note (resolved: `Gate` is not imported in
  `RecommendationsPanel.php` today, confirmed by reading the file directly).
- **Type consistency:** `action(int $id): void` / `dismiss(int $id): void`
  signatures match the existing methods being replaced exactly — no
  signature change, only body changes. `manage-recommendations` string is
  identical across Task 1 Step 3, Task 1 Step 4, and Task 2 Step 1.
