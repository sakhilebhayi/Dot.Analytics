# Recommendation Authorization Gate Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close the missing-authorization gap on `RecommendationsPanel::action()`/`::dismiss()` by reusing this codebase's existing team-owner-or-admin gate pattern, and scope the underlying lookup to the acting user's own team.

**Architecture:** One new named Laravel Gate (`manage-recommendations`) defined in `AppServiceProvider::boot()`, reusing the private `isTeamOwnerOrAdmin()` helper already used by five other gates in that file. `RecommendationsPanel`'s two mutating methods call `Gate::authorize('manage-recommendations')` as their first line (matching `FeatureFlagsPanel`'s exact convention). Cross-tenant scoping needs no new code: `Recommendation` already carries the `HasTeamScope` trait, whose global scope keys every query to `Auth::user()->currentTeam->id` — discovered mid-implementation and corrected in the spec; see that document's Context-section correction note.

**Tech Stack:** Laravel 13, Livewire, PHPUnit, Jetstream teams (owner via `Team::user_id`, `admin` pivot role via `team_user.role`).

## Global Constraints

- Reuse `AppServiceProvider`'s existing private `isTeamOwnerOrAdmin(User $user): bool` helper verbatim — do not duplicate its logic.
- Gate name: exactly `manage-recommendations`, following the existing kebab-case verb-noun convention (`manage-platforms`, `manage-connectors`).
- `Gate::authorize()` (not `Gate::allows()`/`abort_unless()`) — matches `FeatureFlagsPanel`'s exact convention, so a denial throws `Illuminate\Auth\Access\AuthorizationException` → Livewire's test harness surfaces it as an assertable 403.
- Do **not** add an explicit `->where('team_id', ...)` scope to the lookup — `Recommendation`'s own `HasTeamScope` trait already applies that globally; an explicit repeat would be redundant with the trait's own stated design goal (see spec correction note).
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

- [x] **Step 1: Write the failing Livewire authorization tests**

Create `tests/Feature/Livewire/RecommendationsPanelAuthorizationTest.php`:

```php
<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Analytics\RecommendationsPanel;
use App\Models\Recommendation;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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

        // Recommendation::HasTeamScope already applies a global scope keyed
        // on Auth::user()->currentTeam->id, so this lookup finds 0 rows for
        // otherOwner's team and Eloquent throws directly -- Livewire's test
        // call() does not convert this into an assertable HTTP response the
        // way it does for AuthorizationException, so the exception is
        // asserted directly.
        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($otherOwner)
            ->test(RecommendationsPanel::class)
            ->call('action', $recommendation->id);
    }

    public function test_a_different_teams_owner_leaves_this_teams_recommendation_untouched(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $otherOwner = User::factory()->withPersonalTeam()->create();

        $recommendation = Recommendation::factory()->create([
            'team_id' => $owner->currentTeam->id,
            'status' => 'pending',
        ]);

        try {
            Livewire::actingAs($otherOwner)
                ->test(RecommendationsPanel::class)
                ->call('action', $recommendation->id);
        } catch (ModelNotFoundException) {
            // Expected -- see test_a_different_teams_owner_cannot_action_this_teams_recommendation.
        }

        $this->assertSame('pending', $recommendation->fresh()->status);
    }
}
```

**Deviation from the original plan draft, discovered while writing this
step:** the plan originally asserted `->assertStatus(404)` for the
cross-tenant case in a single test, and expected it to already fail
pre-implementation as an ordinary assertion failure. Actually running it
(see Step 2) showed it fails as an **uncaught `ModelNotFoundException`**
instead, both before and after the gate is added — because
`Recommendation`'s `HasTeamScope` global scope already prevents the
cross-tenant read regardless of this fix. The test was split into two
(exception-shape test + untouched-status test) to assert this correctly;
see the spec's Context-section correction for the full explanation.

- [x] **Step 2: Run the tests and observe actual behavior**

Run: `php artisan test --compact tests/Feature/Livewire/RecommendationsPanelAuthorizationTest.php`

Actual result before any implementation change: 2 passed
(`test_team_owner_can_action_a_pending_recommendation`,
`test_team_admin_can_dismiss_a_pending_recommendation` — nothing yet stops
either from succeeding), 1 failed
(`test_non_admin_team_member_is_forbidden` — `assertForbidden()` fails
because the call currently succeeds instead of returning 403), 1 errored
(`test_a_different_teams_owner_cannot_action_this_teams_recommendation` —
uncaught `ModelNotFoundException`, from `HasTeamScope`'s global scope, which
was already active before this fix). This is what actually ran; it
motivated splitting the cross-tenant test as described above.

- [x] **Step 3: Add the `manage-recommendations` gate**

In `app/Providers/AppServiceProvider.php`, inside `boot()`, immediately after
the existing `Gate::define('view-audit-logs', ...)` block and before the
`Gate::define('view-intelligence', ...)` block:

```php
Gate::define('manage-recommendations', fn ($user) => $this->isTeamOwnerOrAdmin($user)
);
```

- [x] **Step 4: Gate `RecommendationsPanel`'s mutating methods**

In `app/Livewire/Analytics/RecommendationsPanel.php`, add the import:

```php
use Illuminate\Support\Facades\Gate;
```

Replace the `action()` and `dismiss()` methods:

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

**No explicit team-scope `where()` clause** — see Global Constraints and the
spec's correction note: `HasTeamScope`'s global scope already does this.

- [x] **Step 5: Run the tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Livewire/RecommendationsPanelAuthorizationTest.php`
Actual: PASS, 5 tests (one more than the original 4-test draft, per the
Step 1 deviation), 0 failures.

- [x] **Step 6: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`
Actual: `passed`.

- [ ] **Step 7: Commit**

```bash
git add app/Providers/AppServiceProvider.php \
  app/Livewire/Analytics/RecommendationsPanel.php \
  tests/Feature/Livewire/RecommendationsPanelAuthorizationTest.php
git commit -m "$(cat <<'EOF'
fix: gate RecommendationsPanel action/dismiss behind team owner/admin

action()/dismiss() previously had no authorization check at all --
any authenticated team member, regardless of role, could flip their
own team's recommendation to actioned/dismissed. Every other mutating
method in this codebase (FeatureFlagsPanel, DataSourcePolicy,
CrossPlatformInsightPolicy) already gates on team owner/admin; this
reuses the same isTeamOwnerOrAdmin() gate pattern via a new
manage-recommendations gate.

Cross-tenant access was never actually open: Recommendation already
carries HasTeamScope, whose global scope keys every query to the
current team, so a cross-tenant id already threw
ModelNotFoundException before this change too -- confirmed by running
the new tests before implementing and seeing that exception, not a
plain assertion failure. No explicit team-scope code was added here;
this commit is authorization-only.

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
