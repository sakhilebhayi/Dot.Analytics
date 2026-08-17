# Custom Dashboards Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn `DashboardBuilderPanel` (real CRUD, but unreachable, unauthorized, and rendering nothing) into a working Custom Dashboards feature — personal and team-shared dashboards, seven real widget types, working drag-to-reorder, and a `/dashboards` page reachable from navigation.

**Architecture:** One migration adds `visibility` to `analytics_dashboards`. A new `AnalyticsDashboardPolicy` (mirroring `DataSourcePolicy`'s existing shape) governs view/create/update/delete. `DashboardBuilderPanel` is rewritten to scope every query through ownership + visibility and gate every mutation through the policy. Five of the seven widget types embed this app's own already-restyled Livewire panels directly (`alerts-panel`, `recommendations-panel`, `cross-platform-insight-panel`, `business-dna-panel`, `executive-briefing-panel`) — real data, no new rendering logic. The other two (`metric_card`, `chart`) are new Blade partials reading `MetricDefinition`/`ComputedMetric`; `chart` is hand-built inline SVG, matching this repo's own established no-JS-charting-library precedent (confirmed by grep — nothing in this codebase pulls in a charting dependency). Sortable.js is added via CDN (currently referenced by the view but never loaded — a live bug) alongside a CSP allowlist entry. A new `/dashboards` route and sidebar nav entry make it reachable without touching the existing `/dashboard` page at all.

**Tech Stack:** Laravel 13, Livewire 3, PHPUnit, Jetstream teams (owner via `Team::user_id`, `admin` pivot role via `team_user.role`), Tailwind CDN + Alpine (bundled via `@livewireScripts`) — no build pipeline for the authenticated layout.

## Global Constraints

- Spec: `docs/superpowers/specs/2026-08-17-custom-dashboards-design.md` — read it first; this plan implements it, with two refinements caught during planning (documented inline in Task 3).
- `visibility` is a plain `string` column (`private`/`team`), not a DB enum — matches this codebase's existing convention for this kind of field (`DataSource.status`, `Recommendation.status` are both plain strings validated at the application layer).
- No metric-computation pipeline. `ComputedMetric` is never written to anywhere in this codebase today (confirmed by grep before this plan was written) — `metric_card`/`chart` must show a clear empty state when their metric has no computed rows, never a blank or broken tile.
- No JS charting library. The `chart` widget is hand-built inline SVG.
- `col`/`width`/`height` on `dashboard_widgets` stay in the schema, unused — the widget grid is a responsive 1–2 column stack ordered by `row` alone. Do not attempt to make them load-bearing.
- Every widget tile's content area is capped `max-height: 420px; overflow-y: auto;` — five of the seven widget types embed full, potentially-tall panels.
- Run `vendor/bin/pint --dirty --format agent` after every task, before committing.
- Run the specific test file(s) for a task after implementing; run the full suite only in the final task.
- Every new model factory follows this repo's existing convention exactly (see `database/factories/RecommendationFactory.php`): `team_id => Team::factory()` where applicable, no manual ID wiring.

---

### Task 1: Migration, model, and factories

**Files:**
- Create: `database/migrations/2026_08_17_000001_add_visibility_to_analytics_dashboards_table.php`
- Modify: `app/Models/AnalyticsDashboard.php`
- Create: `database/factories/AnalyticsDashboardFactory.php`
- Create: `database/factories/DashboardWidgetFactory.php`
- Test: `tests/Feature/Models/AnalyticsDashboardTest.php`

**Interfaces:**
- Consumes: nothing (foundation task).
- Produces: `analytics_dashboards.visibility` column (default `'private'`), `AnalyticsDashboard::factory()`, `DashboardWidget::factory()` — every later task's tests depend on both factories existing.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Models/AnalyticsDashboardTest.php`:

```php
<?php

namespace Tests\Feature\Models;

use App\Models\AnalyticsDashboard;
use App\Models\DashboardWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_visibility_defaults_to_private(): void
    {
        $dashboard = AnalyticsDashboard::factory()->create();

        $this->assertSame('private', $dashboard->fresh()->visibility);
    }

    public function test_visibility_can_be_set_to_team(): void
    {
        $dashboard = AnalyticsDashboard::factory()->create(['visibility' => 'team']);

        $this->assertSame('team', $dashboard->fresh()->visibility);
    }

    public function test_widget_factory_creates_a_widget_linked_to_a_dashboard(): void
    {
        $dashboard = AnalyticsDashboard::factory()->create();
        $widget = DashboardWidget::factory()->create(['analytics_dashboard_id' => $dashboard->id]);

        $this->assertTrue($dashboard->widgets->contains($widget));
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Feature/Models/AnalyticsDashboardTest.php`
Expected: FAIL — `visibility` column doesn't exist yet; `AnalyticsDashboard::factory()` doesn't exist yet.

- [ ] **Step 3: Write the migration**

Create `database/migrations/2026_08_17_000001_add_visibility_to_analytics_dashboards_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analytics_dashboards', function (Blueprint $table) {
            $table->string('visibility')->default('private')->after('is_default');
        });
    }

    public function down(): void
    {
        Schema::table('analytics_dashboards', function (Blueprint $table) {
            $table->dropColumn('visibility');
        });
    }
};
```

Run: `php artisan migrate`

- [ ] **Step 4: Add `visibility` to the model's fillable**

In `app/Models/AnalyticsDashboard.php`, change:

```php
    protected $fillable = [
        'team_id', 'user_id', 'title', 'is_default', 'layout',
    ];
```

to:

```php
    protected $fillable = [
        'team_id', 'user_id', 'title', 'is_default', 'visibility', 'layout',
    ];
```

Add `HasFactory` usage is already present — no other change needed.

- [ ] **Step 5: Write the factories**

Create `database/factories/AnalyticsDashboardFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\AnalyticsDashboard;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnalyticsDashboard>
 */
class AnalyticsDashboardFactory extends Factory
{
    protected $model = AnalyticsDashboard::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'user_id' => User::factory(),
            'title' => $this->faker->words(2, true),
            'is_default' => false,
            'visibility' => 'private',
            'layout' => null,
        ];
    }
}
```

Create `database/factories/DashboardWidgetFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\AnalyticsDashboard;
use App\Models\DashboardWidget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DashboardWidget>
 */
class DashboardWidgetFactory extends Factory
{
    protected $model = DashboardWidget::class;

    public function definition(): array
    {
        return [
            'analytics_dashboard_id' => AnalyticsDashboard::factory(),
            'widget_type' => 'alert_feed',
            'title' => $this->faker->words(2, true),
            'config' => ['type' => 'alert_feed'],
            'col' => 0,
            'row' => 0,
            'width' => 4,
            'height' => 2,
        ];
    }
}
```

`DashboardWidget` needs `use HasFactory;` — check `app/Models/DashboardWidget.php` first: it already has `use HasFactory;` (confirmed by reading the file during spec research), no model change needed here.

- [ ] **Step 6: Run the test to verify it passes**

Run: `php artisan test --compact tests/Feature/Models/AnalyticsDashboardTest.php`
Expected: PASS, 3 tests, 0 failures.

- [ ] **Step 7: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `passed`.

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_08_17_000001_add_visibility_to_analytics_dashboards_table.php \
  app/Models/AnalyticsDashboard.php \
  database/factories/AnalyticsDashboardFactory.php \
  database/factories/DashboardWidgetFactory.php \
  tests/Feature/Models/AnalyticsDashboardTest.php
git commit -m "$(cat <<'EOF'
feat: add visibility to dashboards + factories for the builder

Foundation for Custom Dashboards (see docs/superpowers/specs/2026-08-17-
custom-dashboards-design.md). Adds a private/team visibility column to
analytics_dashboards and the two factories (AnalyticsDashboard,
DashboardWidget) needed by every following task's tests -- neither
existed before this.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 2: `AnalyticsDashboardPolicy`

**Files:**
- Create: `app/Policies/AnalyticsDashboardPolicy.php`
- Test: `tests/Feature/Policies/AnalyticsDashboardPolicyTest.php`

**Interfaces:**
- Consumes: `AnalyticsDashboard::factory()` (Task 1).
- Produces: `view`/`create`/`update`/`delete` abilities on `AnalyticsDashboard`, resolvable via `$user->can(...)` or `Gate::authorize(...)` — Task 3 depends on this.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Policies/AnalyticsDashboardPolicyTest.php`:

```php
<?php

namespace Tests\Feature\Policies;

use App\Models\AnalyticsDashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsDashboardPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_update_and_delete_their_own_private_dashboard(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $dashboard = AnalyticsDashboard::factory()->create([
            'team_id' => $owner->currentTeam->id,
            'user_id' => $owner->id,
            'visibility' => 'private',
        ]);

        $this->assertTrue($owner->can('view', $dashboard));
        $this->assertTrue($owner->can('update', $dashboard));
        $this->assertTrue($owner->can('delete', $dashboard));
    }

    public function test_other_team_member_cannot_view_a_private_dashboard_they_do_not_own(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $member = User::factory()->create();
        $owner->currentTeam->users()->attach($member, ['role' => 'editor']);
        $member->switchTeam($owner->currentTeam);

        $dashboard = AnalyticsDashboard::factory()->create([
            'team_id' => $owner->currentTeam->id,
            'user_id' => $owner->id,
            'visibility' => 'private',
        ]);

        $this->assertFalse($member->can('view', $dashboard));
    }

    public function test_any_team_member_can_view_a_team_visibility_dashboard(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $member = User::factory()->create();
        $owner->currentTeam->users()->attach($member, ['role' => 'editor']);
        $member->switchTeam($owner->currentTeam);

        $dashboard = AnalyticsDashboard::factory()->create([
            'team_id' => $owner->currentTeam->id,
            'user_id' => $owner->id,
            'visibility' => 'team',
        ]);

        $this->assertTrue($member->can('view', $dashboard));
    }

    public function test_team_admin_can_update_and_delete_a_team_visibility_dashboard_they_do_not_own(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $admin = User::factory()->create();
        $owner->currentTeam->users()->attach($admin, ['role' => 'admin']);
        $admin->switchTeam($owner->currentTeam);

        $dashboard = AnalyticsDashboard::factory()->create([
            'team_id' => $owner->currentTeam->id,
            'user_id' => $owner->id,
            'visibility' => 'team',
        ]);

        $this->assertTrue($admin->can('update', $dashboard));
        $this->assertTrue($admin->can('delete', $dashboard));
    }

    public function test_regular_member_cannot_update_a_team_visibility_dashboard_they_do_not_own(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $member = User::factory()->create();
        $owner->currentTeam->users()->attach($member, ['role' => 'editor']);
        $member->switchTeam($owner->currentTeam);

        $dashboard = AnalyticsDashboard::factory()->create([
            'team_id' => $owner->currentTeam->id,
            'user_id' => $owner->id,
            'visibility' => 'team',
        ]);

        $this->assertFalse($member->can('update', $dashboard));
        $this->assertFalse($member->can('delete', $dashboard));
    }

    public function test_admin_still_cannot_update_a_private_dashboard_they_do_not_own(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $admin = User::factory()->create();
        $owner->currentTeam->users()->attach($admin, ['role' => 'admin']);
        $admin->switchTeam($owner->currentTeam);

        $dashboard = AnalyticsDashboard::factory()->create([
            'team_id' => $owner->currentTeam->id,
            'user_id' => $owner->id,
            'visibility' => 'private',
        ]);

        $this->assertFalse($admin->can('update', $dashboard));
        $this->assertFalse($admin->can('delete', $dashboard));
    }

    public function test_a_different_teams_user_cannot_view_a_team_visibility_dashboard(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $outsider = User::factory()->withPersonalTeam()->create();

        $dashboard = AnalyticsDashboard::factory()->create([
            'team_id' => $owner->currentTeam->id,
            'user_id' => $owner->id,
            'visibility' => 'team',
        ]);

        $this->assertFalse($outsider->can('view', $dashboard));
    }

    public function test_owner_who_switched_to_a_different_current_team_cannot_update_their_own_old_dashboard(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $otherTeamOwner = User::factory()->withPersonalTeam()->create();
        $otherTeamOwner->currentTeam->users()->attach($owner, ['role' => 'editor']);

        $dashboard = AnalyticsDashboard::factory()->create([
            'team_id' => $owner->currentTeam->id,
            'user_id' => $owner->id,
            'visibility' => 'private',
        ]);

        // $owner switches active team away from the team the dashboard belongs to.
        $owner->switchTeam($otherTeamOwner->currentTeam);

        $this->assertFalse($owner->can('view', $dashboard));
        $this->assertFalse($owner->can('update', $dashboard));
    }
}
```

The last test covers a real edge case found while writing this plan (not in the original spec text): ownership alone (`user_id` match) is not sufficient for `update`/`delete` — the acting user's *current* team must also match the dashboard's team, exactly like `view` already requires. Without that check, a user who owns a dashboard under Team A but has since switched their active team to Team B could still update/delete it while "in" Team B, while `view` would deny them the same dashboard in that state — an inconsistent, confusing contradiction. The policy below closes this.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Policies/AnalyticsDashboardPolicyTest.php`
Expected: FAIL/ERROR on all 8 — `AnalyticsDashboardPolicy` doesn't exist yet, so `$user->can(...)` has no policy to resolve against (Laravel returns `false` for unregistered abilities without a policy, so most assertions fail rather than error, but confirm actual output before continuing).

- [ ] **Step 3: Write the policy**

Create `app/Policies/AnalyticsDashboardPolicy.php`:

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
        if ($user->currentTeam?->id !== $dashboard->team_id) {
            return false;
        }

        if ($dashboard->user_id === $user->id) {
            return true;
        }

        return $dashboard->visibility === 'team' && $this->isTeamOwnerOrAdmin($user, $dashboard->team_id);
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

No manual registration needed — Laravel's naming-convention auto-discovery
(`AnalyticsDashboard` → `AnalyticsDashboardPolicy`) picks this up
automatically, confirmed by grep during spec research: neither
`DataSourcePolicy` nor `CrossPlatformInsightPolicy` (this codebase's two
existing model policies) has a manual `Gate::policy()` binding anywhere in
`AppServiceProvider`.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Policies/AnalyticsDashboardPolicyTest.php`
Expected: PASS, 8 tests, 0 failures.

- [ ] **Step 5: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `passed`.

- [ ] **Step 6: Commit**

```bash
git add app/Policies/AnalyticsDashboardPolicy.php tests/Feature/Policies/AnalyticsDashboardPolicyTest.php
git commit -m "$(cat <<'EOF'
feat: add AnalyticsDashboardPolicy for dashboard ownership + sharing

Mirrors DataSourcePolicy's existing shape. Owner always has full
access; a visibility='team' dashboard can be viewed by any team
member but only updated/deleted by its owner or a team admin/owner;
a private dashboard stays owner-only regardless of role.

Also closes an edge case found while writing this policy's tests: an
owner whose current team has since switched away from the dashboard's
team must not retain update/delete access just because user_id
matches -- update()/delete() now require current-team match first,
exactly like view() already does, so a user can't act on a dashboard
they could no longer even view.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 3: `DashboardBuilderPanel` — ownership, gating, per-owner default

**Files:**
- Modify: `app/Livewire/Analytics/DashboardBuilderPanel.php`
- Test: `tests/Feature/Livewire/DashboardBuilderPanelTest.php`

**Interfaces:**
- Consumes: `AnalyticsDashboardPolicy` (Task 2), `AnalyticsDashboard::factory()`/`DashboardWidget::factory()` (Task 1).
- Produces: `DashboardBuilderPanel::METRIC_WIDGET_TYPES` (new public const, consumed by Task 6's view), public property `$widgetMetricDefinitionId` (consumed by Task 6's metric picker), public property `$newDashboardVisibility` (consumed by Task 6's create form), `#[Computed] metricDefinitions()` (consumed by Task 6).

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Livewire/DashboardBuilderPanelTest.php`:

```php
<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Analytics\DashboardBuilderPanel;
use App\Models\AnalyticsDashboard;
use App\Models\DashboardWidget;
use App\Models\MetricDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardBuilderPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_dashboard_sets_owner_team_and_makes_it_the_users_default(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        Livewire::actingAs($user)
            ->test(DashboardBuilderPanel::class)
            ->set('newDashboardTitle', 'Ops Overview')
            ->set('newDashboardVisibility', 'private')
            ->call('createDashboard');

        $dashboard = AnalyticsDashboard::sole();
        $this->assertSame($user->id, $dashboard->user_id);
        $this->assertSame($user->currentTeam->id, $dashboard->team_id);
        $this->assertSame('private', $dashboard->visibility);
        $this->assertTrue($dashboard->is_default);
    }

    public function test_a_second_dashboard_for_the_same_user_is_not_default(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        AnalyticsDashboard::factory()->create([
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'is_default' => true,
        ]);

        Livewire::actingAs($user)
            ->test(DashboardBuilderPanel::class)
            ->set('newDashboardTitle', 'Second One')
            ->set('newDashboardVisibility', 'private')
            ->call('createDashboard');

        $newest = AnalyticsDashboard::where('title', 'Second One')->sole();
        $this->assertFalse($newest->is_default);
    }

    public function test_dashboards_list_includes_mine_and_shared_team_ones_but_not_other_members_private_ones(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $teammate = User::factory()->create();
        $user->currentTeam->users()->attach($teammate, ['role' => 'editor']);

        $mine = AnalyticsDashboard::factory()->create(['team_id' => $user->currentTeam->id, 'user_id' => $user->id, 'visibility' => 'private']);
        $sharedByTeammate = AnalyticsDashboard::factory()->create(['team_id' => $user->currentTeam->id, 'user_id' => $teammate->id, 'visibility' => 'team']);
        $teammatesPrivate = AnalyticsDashboard::factory()->create(['team_id' => $user->currentTeam->id, 'user_id' => $teammate->id, 'visibility' => 'private']);

        $component = Livewire::actingAs($user)->test(DashboardBuilderPanel::class);
        $ids = $component->get('dashboards')->pluck('id');

        $this->assertTrue($ids->contains($mine->id));
        $this->assertTrue($ids->contains($sharedByTeammate->id));
        $this->assertFalse($ids->contains($teammatesPrivate->id));
    }

    public function test_setting_default_only_unsets_it_on_dashboards_owned_by_that_dashboards_owner(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $ownersFirst = AnalyticsDashboard::factory()->create(['team_id' => $owner->currentTeam->id, 'user_id' => $owner->id, 'is_default' => true]);
        $ownersSecond = AnalyticsDashboard::factory()->create(['team_id' => $owner->currentTeam->id, 'user_id' => $owner->id, 'is_default' => false]);

        $admin = User::factory()->create();
        $owner->currentTeam->users()->attach($admin, ['role' => 'admin']);
        $adminsOwn = AnalyticsDashboard::factory()->create(['team_id' => $owner->currentTeam->id, 'user_id' => $admin->id, 'is_default' => true, 'visibility' => 'private']);

        Livewire::actingAs($owner)
            ->test(DashboardBuilderPanel::class)
            ->call('setDefault', $ownersSecond->id);

        $this->assertFalse($ownersFirst->fresh()->is_default);
        $this->assertTrue($ownersSecond->fresh()->is_default);
        $this->assertTrue($adminsOwn->fresh()->is_default, 'a different owner\'s default must be untouched');
    }

    public function test_a_regular_member_cannot_delete_a_shared_dashboard_they_do_not_own(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $member = User::factory()->create();
        $owner->currentTeam->users()->attach($member, ['role' => 'editor']);
        $member->switchTeam($owner->currentTeam);

        $shared = AnalyticsDashboard::factory()->create(['team_id' => $owner->currentTeam->id, 'user_id' => $owner->id, 'visibility' => 'team']);

        Livewire::actingAs($member)
            ->test(DashboardBuilderPanel::class)
            ->call('deleteDashboard', $shared->id)
            ->assertForbidden();

        $this->assertNotNull($shared->fresh());
    }

    public function test_a_team_admin_can_add_a_widget_to_a_shared_dashboard_they_do_not_own(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $admin = User::factory()->create();
        $owner->currentTeam->users()->attach($admin, ['role' => 'admin']);
        $admin->switchTeam($owner->currentTeam);

        $shared = AnalyticsDashboard::factory()->create(['team_id' => $owner->currentTeam->id, 'user_id' => $owner->id, 'visibility' => 'team']);

        Livewire::actingAs($admin)
            ->test(DashboardBuilderPanel::class)
            ->call('selectDashboard', $shared->id)
            ->set('widgetType', 'alert_feed')
            ->call('addWidget');

        $this->assertSame(1, DashboardWidget::where('analytics_dashboard_id', $shared->id)->count());
    }

    public function test_a_metric_widget_requires_a_metric_definition(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $dashboard = AnalyticsDashboard::factory()->create(['team_id' => $user->currentTeam->id, 'user_id' => $user->id]);

        Livewire::actingAs($user)
            ->test(DashboardBuilderPanel::class)
            ->call('selectDashboard', $dashboard->id)
            ->set('widgetType', 'metric_card')
            ->call('addWidget')
            ->assertHasErrors(['widgetMetricDefinitionId']);

        $this->assertSame(0, DashboardWidget::where('analytics_dashboard_id', $dashboard->id)->count());
    }

    public function test_a_metric_widget_stores_the_chosen_metric_in_config(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $dashboard = AnalyticsDashboard::factory()->create(['team_id' => $user->currentTeam->id, 'user_id' => $user->id]);
        $definition = MetricDefinition::factory()->create();

        Livewire::actingAs($user)
            ->test(DashboardBuilderPanel::class)
            ->call('selectDashboard', $dashboard->id)
            ->set('widgetType', 'metric_card')
            ->set('widgetMetricDefinitionId', $definition->id)
            ->call('addWidget');

        $widget = DashboardWidget::where('analytics_dashboard_id', $dashboard->id)->sole();
        $this->assertSame($definition->id, $widget->config['metric_definition_id']);
    }

    public function test_removing_a_widget_from_someone_elses_private_dashboard_is_forbidden(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $member = User::factory()->create();
        $owner->currentTeam->users()->attach($member, ['role' => 'editor']);
        $member->switchTeam($owner->currentTeam);

        $dashboard = AnalyticsDashboard::factory()->create(['team_id' => $owner->currentTeam->id, 'user_id' => $owner->id, 'visibility' => 'team']);
        $widget = DashboardWidget::factory()->create(['analytics_dashboard_id' => $dashboard->id]);

        Livewire::actingAs($member)
            ->test(DashboardBuilderPanel::class)
            ->call('removeWidget', $widget->id)
            ->assertForbidden();

        $this->assertNotNull($widget->fresh());
    }

    public function test_reordering_widgets_persists_new_row_order(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $dashboard = AnalyticsDashboard::factory()->create(['team_id' => $user->currentTeam->id, 'user_id' => $user->id]);
        $first = DashboardWidget::factory()->create(['analytics_dashboard_id' => $dashboard->id, 'row' => 0]);
        $second = DashboardWidget::factory()->create(['analytics_dashboard_id' => $dashboard->id, 'row' => 1]);

        Livewire::actingAs($user)
            ->test(DashboardBuilderPanel::class)
            ->call('selectDashboard', $dashboard->id)
            ->call('updatePositions', [$second->id, $first->id]);

        $this->assertSame(0, $second->fresh()->row);
        $this->assertSame(0, $first->fresh()->row);
        // Both land in row 0 (positions 0 and 1 both floor-divide to row 0
        // under the existing 3-per-row math) -- ordering within a row is by
        // `col`, confirmed by the assertion below.
        $this->assertTrue($second->fresh()->col < $first->fresh()->col);
    }
}
```

The reordering test's row/col expectation follows the existing (unchanged)
`updatePositions()` math (`row = floor(position / 3)`, `col = (position % 3)
* 4`) — position 0 → row 0, col 0; position 1 → row 0, col 4. Confirmed by
reading that method before writing this test, not assumed.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Livewire/DashboardBuilderPanelTest.php`
Expected: multiple failures — `newDashboardVisibility` property doesn't
exist, `dashboards` isn't scoped by owner/visibility yet, no gate calls
exist yet (`assertForbidden()` calls fail because nothing is forbidden),
`widgetMetricDefinitionId` doesn't exist.

- [ ] **Step 3: Rewrite `DashboardBuilderPanel`**

Replace the full contents of `app/Livewire/Analytics/DashboardBuilderPanel.php`:

```php
<?php

namespace App\Livewire\Analytics;

use App\Models\AnalyticsDashboard;
use App\Models\DashboardWidget;
use App\Models\MetricDefinition;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Dashboard Builder Panel
 *
 * Lets team members compose custom intelligence dashboards from widgets.
 * A dashboard is either private (visible only to its creator) or shared
 * with the team (visible to everyone, editable by its creator or a team
 * admin/owner) -- see AnalyticsDashboardPolicy for the exact rules.
 *
 * Five widget types embed this app's own alert/recommendation/insight/DNA/
 * briefing panels directly. The other two (metric_card, chart) read
 * MetricDefinition/ComputedMetric and are rendered by dedicated Blade
 * partials under resources/views/livewire/analytics/widgets/.
 *
 * Drag-and-drop reordering is handled client-side via Alpine.js + Sortable.js
 * and persists position via the `updatePositions` action.
 */
class DashboardBuilderPanel extends Component
{
    public ?int $activeDashboardId = null;

    public string $newDashboardTitle = '';

    public string $newDashboardVisibility = 'private';

    public bool $showNewDashboard = false;

    // Widget form state
    public string $widgetType = 'metric_card';

    public string $widgetTitle = '';

    public ?int $widgetMetricDefinitionId = null;

    public bool $showAddWidget = false;

    public const WIDGET_TYPES = [
        'metric_card' => 'Metric Card',
        'chart' => 'Chart',
        'alert_feed' => 'Alert Feed',
        'recommendation_feed' => 'Recommendation Feed',
        'insight_feed' => 'Cross-Platform Insights',
        'dna_snapshot' => 'Business DNA Snapshot',
        'briefing_summary' => 'Executive Briefing Summary',
    ];

    /** Widget types that require picking a MetricDefinition. */
    public const METRIC_WIDGET_TYPES = ['metric_card', 'chart'];

    protected function rules(): array
    {
        return [
            'newDashboardTitle' => 'required|string|max:120',
            'newDashboardVisibility' => 'required|in:private,team',
            'widgetType' => 'required|string|in:'.implode(',', array_keys(self::WIDGET_TYPES)),
            'widgetTitle' => 'nullable|string|max:120',
            'widgetMetricDefinitionId' => 'required_if:widgetType,'.implode(',', self::METRIC_WIDGET_TYPES).'|nullable|exists:metric_definitions,id',
        ];
    }

    #[Computed]
    public function dashboards(): Collection
    {
        return AnalyticsDashboard::where(function ($query) {
            $query->where('user_id', Auth::id())
                ->orWhere('visibility', 'team');
        })
            ->orderBy('is_default', 'desc')
            ->orderBy('title')
            ->get();
    }

    #[Computed]
    public function activeDashboard(): ?AnalyticsDashboard
    {
        if (! $this->activeDashboardId) {
            return $this->dashboards->first();
        }

        return $this->dashboards->firstWhere('id', $this->activeDashboardId);
    }

    #[Computed]
    public function widgets(): Collection
    {
        return $this->activeDashboard
            ? DashboardWidget::where('analytics_dashboard_id', $this->activeDashboard->id)
                ->orderBy('row')->orderBy('col')
                ->get()
            : collect();
    }

    #[Computed]
    public function metricDefinitions(): Collection
    {
        return MetricDefinition::orderBy('label')->get();
    }

    public function createDashboard(): void
    {
        Gate::authorize('create', AnalyticsDashboard::class);
        $this->validateOnly('newDashboardTitle');
        $this->validateOnly('newDashboardVisibility');

        $team = Auth::user()->currentTeam;
        $isFirstOwnDashboard = ! AnalyticsDashboard::where('user_id', Auth::id())->exists();

        $dashboard = AnalyticsDashboard::create([
            'team_id' => $team->id,
            'user_id' => Auth::id(),
            'title' => $this->newDashboardTitle,
            'visibility' => $this->newDashboardVisibility,
            'is_default' => $isFirstOwnDashboard,
        ]);

        $this->activeDashboardId = $dashboard->id;
        $this->reset(['newDashboardTitle', 'newDashboardVisibility', 'showNewDashboard']);
        unset($this->dashboards, $this->activeDashboard);
    }

    public function selectDashboard(int $id): void
    {
        $dashboard = AnalyticsDashboard::findOrFail($id);
        Gate::authorize('view', $dashboard);

        $this->activeDashboardId = $id;
        unset($this->activeDashboard, $this->widgets);
    }

    public function setDefault(int $id): void
    {
        $dashboard = AnalyticsDashboard::findOrFail($id);
        Gate::authorize('update', $dashboard);

        AnalyticsDashboard::where('user_id', $dashboard->user_id)->update(['is_default' => false]);
        $dashboard->update(['is_default' => true]);

        unset($this->dashboards);
    }

    public function deleteDashboard(int $id): void
    {
        $dashboard = AnalyticsDashboard::findOrFail($id);
        Gate::authorize('delete', $dashboard);

        $dashboard->delete();

        if ($this->activeDashboardId === $id) {
            $this->activeDashboardId = null;
        }

        unset($this->dashboards, $this->activeDashboard, $this->widgets);
    }

    public function addWidget(): void
    {
        $dashboard = $this->activeDashboard;
        if (! $dashboard) {
            return;
        }
        Gate::authorize('update', $dashboard);

        $this->validateOnly('widgetType');
        $this->validateOnly('widgetMetricDefinitionId');

        // Place widget in next available grid position
        $existingCount = DashboardWidget::where('analytics_dashboard_id', $dashboard->id)->count();
        $col = ($existingCount * 4) % 12;
        $row = (int) floor(($existingCount * 4) / 12);

        $config = ['type' => $this->widgetType];
        if (in_array($this->widgetType, self::METRIC_WIDGET_TYPES, true)) {
            $config['metric_definition_id'] = $this->widgetMetricDefinitionId;
        }

        DashboardWidget::create([
            'analytics_dashboard_id' => $dashboard->id,
            'widget_type' => $this->widgetType,
            'title' => $this->widgetTitle ?: self::WIDGET_TYPES[$this->widgetType] ?? $this->widgetType,
            'col' => $col,
            'row' => $row,
            'width' => 4,
            'height' => 2,
            'config' => $config,
        ]);

        $this->reset(['widgetType', 'widgetTitle', 'widgetMetricDefinitionId', 'showAddWidget']);
        unset($this->widgets);
    }

    public function removeWidget(int $id): void
    {
        $widget = DashboardWidget::with('dashboard')->findOrFail($id);
        if (! $widget->dashboard) {
            // The parent dashboard is outside the acting user's team --
            // AnalyticsDashboard's HasTeamScope global scope hides it from
            // the belongsTo lookup, so this looks the same as "not found".
            abort(404);
        }
        Gate::authorize('update', $widget->dashboard);

        $widget->delete();

        unset($this->widgets);
    }

    /**
     * Persist widget positions after drag-and-drop reorder.
     * Called from Alpine.js with the new order as an array of IDs.
     *
     * @param  array<int, int>  $orderedIds
     */
    public function updatePositions(array $orderedIds): void
    {
        $dashboard = $this->activeDashboard;
        if (! $dashboard) {
            return;
        }
        Gate::authorize('update', $dashboard);

        foreach ($orderedIds as $position => $widgetId) {
            DashboardWidget::where('analytics_dashboard_id', $dashboard->id)
                ->where('id', $widgetId)
                ->update([
                    'row' => (int) floor($position / 3),
                    'col' => ($position % 3) * 4,
                ]);
        }

        unset($this->widgets);
    }

    public function render(): View
    {
        return view('livewire.analytics.dashboard-builder-panel');
    }
}
```

Three behavioral changes from the original code, beyond what's listed in
Global Constraints:
1. `activeDashboard()` resolves through the already-authorization-filtered
   `$this->dashboards` collection (`firstWhere`) instead of a raw
   `AnalyticsDashboard::find()` — a defensive default so a stale/tampered
   `activeDashboardId` can never resolve to a dashboard outside what the
   viewer can already see, even before any explicit gate check runs.
2. `removeWidget()` explicitly checks `$widget->dashboard` for null before
   authorizing — `DashboardWidget` has no team scope of its own, but its
   `dashboard()` relation loads through `AnalyticsDashboard`, which does;
   a widget whose parent dashboard belongs to another team resolves that
   relation to `null` rather than throwing, and passing `null` to
   `Gate::authorize('update', null)` would throw a raw `TypeError` (ugly
   500) instead of a clean 404 without this check.
3. `updatePositions()` scopes its update to `analytics_dashboard_id =
   $dashboard->id` (previously `whereHas('dashboard')`, an existence check
   with no team-specific constraint beyond what the relation's own global
   scope implied) — equivalent in effect once ownership is already
   authorized above, but explicit rather than relying on an incidental
   side effect of `whereHas`.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Livewire/DashboardBuilderPanelTest.php`
Expected: PASS, 10 tests, 0 failures.

- [ ] **Step 5: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `passed`.

- [ ] **Step 6: Commit**

```bash
git add app/Livewire/Analytics/DashboardBuilderPanel.php tests/Feature/Livewire/DashboardBuilderPanelTest.php
git commit -m "$(cat <<'EOF'
feat: scope DashboardBuilderPanel by ownership + gate every mutation

dashboards() previously returned every dashboard on the team with no
user_id filter, and setDefault() mass-updated is_default across the
whole team -- no ownership model, no authorization at all. Now scoped
to "mine, plus team-shared ones" (AnalyticsDashboardPolicy, previous
commit), is_default is per-owner, and every mutating method is gated.

Also adds the metric-widget config plumbing (widgetMetricDefinitionId,
METRIC_WIDGET_TYPES, metricDefinitions()) that the metric_card/chart
widget types (next commits) and the view (final commit) depend on.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 4: `metric_card` widget

**Files:**
- Create: `database/factories/ComputedMetricFactory.php`
- Create: `resources/views/livewire/analytics/widgets/metric-card.blade.php`
- Test: `tests/Feature/Livewire/MetricCardWidgetTest.php`

**Interfaces:**
- Consumes: `DashboardWidget` (`config['metric_definition_id']`, from Task 3).
- Produces: `livewire.analytics.widgets.metric-card` view, included by Task 6's widget tile switch with `['widget' => $widget]`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Livewire/MetricCardWidgetTest.php`:

```php
<?php

namespace Tests\Feature\Livewire;

use App\Models\ComputedMetric;
use App\Models\DashboardWidget;
use App\Models\MetricDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetricCardWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_the_most_recent_computed_value_and_unit(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);

        $definition = MetricDefinition::factory()->create(['label' => 'Fleet Utilisation', 'unit' => '%']);
        ComputedMetric::factory()->create([
            'team_id' => $user->currentTeam->id,
            'metric_definition_id' => $definition->id,
            'value' => 82.5,
            'period_date' => '2026-08-01',
        ]);
        ComputedMetric::factory()->create([
            'team_id' => $user->currentTeam->id,
            'metric_definition_id' => $definition->id,
            'value' => 91.25,
            'period_date' => '2026-08-15',
        ]);

        $widget = new DashboardWidget(['config' => ['metric_definition_id' => $definition->id]]);

        $html = view('livewire.analytics.widgets.metric-card', ['widget' => $widget])->render();

        $this->assertStringContainsString('Fleet Utilisation', $html);
        $this->assertStringContainsString('91.25', $html);
        $this->assertStringContainsString('%', $html);
        $this->assertStringNotContainsString('82.50', $html);
    }

    public function test_shows_an_empty_state_when_no_computed_value_exists_yet(): void
    {
        $definition = MetricDefinition::factory()->create(['label' => 'Support Ticket Backlog']);
        $widget = new DashboardWidget(['config' => ['metric_definition_id' => $definition->id]]);

        $html = view('livewire.analytics.widgets.metric-card', ['widget' => $widget])->render();

        $this->assertStringContainsString('No computed value yet for Support Ticket Backlog', $html);
    }

    public function test_shows_a_graceful_message_if_the_metric_definition_no_longer_exists(): void
    {
        $widget = new DashboardWidget(['config' => ['metric_definition_id' => 999999]]);

        $html = view('livewire.analytics.widgets.metric-card', ['widget' => $widget])->render();

        $this->assertStringContainsString('Metric no longer exists', $html);
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Livewire/MetricCardWidgetTest.php`
Expected: FAIL — `ComputedMetric::factory()` doesn't exist yet; the view doesn't exist yet.

- [ ] **Step 3: Write the `ComputedMetric` factory**

Create `database/factories/ComputedMetricFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\ComputedMetric;
use App\Models\MetricDefinition;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComputedMetric>
 */
class ComputedMetricFactory extends Factory
{
    protected $model = ComputedMetric::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'metric_definition_id' => MetricDefinition::factory(),
            'value' => $this->faker->randomFloat(2, 0, 1000),
            'period' => 'daily',
            'period_date' => $this->faker->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
        ];
    }
}
```

`ComputedMetric` needs `use HasFactory;` — check `app/Models/ComputedMetric.php`
first (read during spec research: it currently uses only `HasTeamScope`, not
`HasFactory`). Add it:

```php
use Illuminate\Database\Eloquent\Factories\HasFactory;
// ...
class ComputedMetric extends Model
{
    use HasFactory, HasTeamScope;
```

- [ ] **Step 4: Write the widget partial**

Create `resources/views/livewire/analytics/widgets/metric-card.blade.php`:

```blade
@php
    $definition = \App\Models\MetricDefinition::find($widget->config['metric_definition_id'] ?? null);
    $latest = $definition
        ? \App\Models\ComputedMetric::where('metric_definition_id', $definition->id)
            ->latest('period_date')
            ->first()
        : null;
@endphp

@if(! $definition)
    <p style="font-size:0.8rem;color:var(--mist);">Metric no longer exists.</p>
@elseif(! $latest)
    <p style="font-size:0.8rem;color:var(--mist);">No computed value yet for {{ $definition->label }}.</p>
@else
    <p class="font-mono" style="font-size:0.62rem;letter-spacing:0.08em;text-transform:uppercase;color:var(--mist);margin:0 0 0.4rem;">{{ $definition->label }}</p>
    <p class="font-display" style="font-size:1.8rem;font-weight:700;color:var(--paper);margin:0;">
        {{ number_format((float) $latest->value, 2) }}
        <span style="font-size:0.9rem;font-weight:500;color:var(--mist);">{{ $definition->unit }}</span>
    </p>
    <p style="font-size:0.68rem;color:var(--mist);opacity:0.7;margin:0.3rem 0 0;">as of {{ $latest->period_date->format('d M Y') }}</p>
@endif
```

`ComputedMetric` carries `HasTeamScope`, so `where('metric_definition_id',
...)->latest(...)` is already implicitly scoped to the acting user's current
team by the model's own global scope — no explicit `team_id` filter needed
here, consistent with this codebase's established convention (see
`RecommendationsPanel`'s own reliance on the same trait).

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Livewire/MetricCardWidgetTest.php`
Expected: PASS, 3 tests, 0 failures.

- [ ] **Step 6: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `passed`.

- [ ] **Step 7: Commit**

```bash
git add database/factories/ComputedMetricFactory.php \
  app/Models/ComputedMetric.php \
  resources/views/livewire/analytics/widgets/metric-card.blade.php \
  tests/Feature/Livewire/MetricCardWidgetTest.php
git commit -m "$(cat <<'EOF'
feat: metric_card dashboard widget

Reads a widget's configured MetricDefinition and shows its most
recent ComputedMetric value, formatted with the definition's unit.
ComputedMetric is never written to anywhere in this codebase today
(confirmed during spec research), so the common case in practice is
the empty state -- deliberately a clear message, not a blank tile.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 5: `chart` widget (hand-built SVG)

**Files:**
- Create: `resources/views/livewire/analytics/widgets/metric-chart.blade.php`
- Test: `tests/Feature/Livewire/MetricChartWidgetTest.php`

**Interfaces:**
- Consumes: `ComputedMetric::factory()` (Task 4), `DashboardWidget` config shape (Task 3).
- Produces: `livewire.analytics.widgets.metric-chart` view, included by Task 6's widget tile switch.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Livewire/MetricChartWidgetTest.php`:

```php
<?php

namespace Tests\Feature\Livewire;

use App\Models\ComputedMetric;
use App\Models\DashboardWidget;
use App\Models\MetricDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetricChartWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_renders_a_polyline_through_the_computed_history(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);

        $definition = MetricDefinition::factory()->create(['label' => 'Overtime Hours']);
        foreach ([10, 25, 15, 40] as $i => $value) {
            ComputedMetric::factory()->create([
                'team_id' => $user->currentTeam->id,
                'metric_definition_id' => $definition->id,
                'value' => $value,
                'period_date' => now()->subDays(10 - $i * 2)->format('Y-m-d'),
            ]);
        }

        $widget = new DashboardWidget(['config' => ['metric_definition_id' => $definition->id]]);

        $html = view('livewire.analytics.widgets.metric-chart', ['widget' => $widget])->render();

        $this->assertStringContainsString('<polyline', $html);
        $this->assertStringContainsString('Overtime Hours', $html);
    }

    public function test_shows_an_empty_state_with_fewer_than_two_points(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);

        $definition = MetricDefinition::factory()->create(['label' => 'Idle Time']);
        ComputedMetric::factory()->create([
            'team_id' => $user->currentTeam->id,
            'metric_definition_id' => $definition->id,
        ]);

        $widget = new DashboardWidget(['config' => ['metric_definition_id' => $definition->id]]);

        $html = view('livewire.analytics.widgets.metric-chart', ['widget' => $widget])->render();

        $this->assertStringContainsString('No computed history yet for Idle Time', $html);
        $this->assertStringNotContainsString('<polyline', $html);
    }

    public function test_shows_an_empty_state_with_zero_points(): void
    {
        $definition = MetricDefinition::factory()->create(['label' => 'Downtime']);
        $widget = new DashboardWidget(['config' => ['metric_definition_id' => $definition->id]]);

        $html = view('livewire.analytics.widgets.metric-chart', ['widget' => $widget])->render();

        $this->assertStringContainsString('No computed history yet for Downtime', $html);
    }

    public function test_flat_history_does_not_error_on_division_by_zero(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);

        $definition = MetricDefinition::factory()->create(['label' => 'Constant Metric']);
        foreach (range(1, 3) as $i) {
            ComputedMetric::factory()->create([
                'team_id' => $user->currentTeam->id,
                'metric_definition_id' => $definition->id,
                'value' => 50,
                'period_date' => now()->subDays(3 - $i)->format('Y-m-d'),
            ]);
        }

        $widget = new DashboardWidget(['config' => ['metric_definition_id' => $definition->id]]);

        $html = view('livewire.analytics.widgets.metric-chart', ['widget' => $widget])->render();

        $this->assertStringContainsString('<polyline', $html);
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Livewire/MetricChartWidgetTest.php`
Expected: FAIL — the view doesn't exist yet.

- [ ] **Step 3: Write the widget partial**

Create `resources/views/livewire/analytics/widgets/metric-chart.blade.php`:

```blade
@php
    $definition = \App\Models\MetricDefinition::find($widget->config['metric_definition_id'] ?? null);
    $points = $definition
        ? \App\Models\ComputedMetric::where('metric_definition_id', $definition->id)
            ->orderBy('period_date', 'desc')
            ->limit(12)
            ->get()
            ->sortBy('period_date')
            ->values()
        : collect();
@endphp

@if(! $definition)
    <p style="font-size:0.8rem;color:var(--mist);">Metric no longer exists.</p>
@elseif($points->count() < 2)
    <p style="font-size:0.8rem;color:var(--mist);">No computed history yet for {{ $definition->label }}.</p>
@else
    @php
        $chartW = 320;
        $chartH = 90;
        $values = $points->map(fn ($p) => (float) $p->value);
        $min = $values->min();
        $max = $values->max();
        $range = ($max - $min) > 0 ? ($max - $min) : 1;
        $step = $chartW / ($points->count() - 1);
        $coords = $points->values()->map(function ($point, $i) use ($step, $chartH, $min, $range) {
            $x = round($i * $step, 2);
            $y = round($chartH - ((((float) $point->value) - $min) / $range) * $chartH, 2);
            return "{$x},{$y}";
        })->implode(' ');
    @endphp
    <p class="font-mono" style="font-size:0.62rem;letter-spacing:0.08em;text-transform:uppercase;color:var(--mist);margin:0 0 0.5rem;">{{ $definition->label }}</p>
    <svg viewBox="0 0 {{ $chartW }} {{ $chartH }}" style="width:100%;height:auto;overflow:visible;" xmlns="http://www.w3.org/2000/svg">
        <polyline points="{{ $coords }}" fill="none" stroke="var(--gold)" stroke-width="2" />
        @foreach($points as $i => $point)
            @php
                $x = round($i * $step, 2);
                $y = round($chartH - ((((float) $point->value) - $min) / $range) * $chartH, 2);
            @endphp
            <circle cx="{{ $x }}" cy="{{ $y }}" r="2.5" fill="var(--teal-soft)" />
        @endforeach
    </svg>
    <p style="font-size:0.68rem;color:var(--mist);opacity:0.7;margin:0.4rem 0 0;">{{ $points->first()->period_date->format('d M') }} &ndash; {{ $points->last()->period_date->format('d M Y') }}</p>
@endif
```

Same visual grammar as `dashboard.blade.php`'s KPI strip from `56002bf` —
gold line, teal-soft points. Last 12 `ComputedMetric` rows by `period_date`,
fetched descending (to limit to the *most recent* 12) then re-sorted
ascending for left-to-right chart order. `$range` falls back to `1` when
`$max === $min` (a flat/constant history) so the division can't produce
`NAN` — covered by
`test_flat_history_does_not_error_on_division_by_zero` above.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Livewire/MetricChartWidgetTest.php`
Expected: PASS, 4 tests, 0 failures.

- [ ] **Step 5: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `passed`.

- [ ] **Step 6: Commit**

```bash
git add resources/views/livewire/analytics/widgets/metric-chart.blade.php \
  tests/Feature/Livewire/MetricChartWidgetTest.php
git commit -m "$(cat <<'EOF'
feat: chart dashboard widget (hand-built inline SVG)

No charting library exists anywhere in this codebase (confirmed by
grep during spec research) -- this follows the same hand-built-SVG
pattern this repo's own KPI strip (56002bf) already established,
rather than adding a new CDN dependency. Last 12 ComputedMetric points
for the widget's configured metric, gold polyline + teal-soft points.
Falls back cleanly to an empty state below 2 points, and to a
non-zero range denominator on flat/constant history.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 6: Load Sortable.js + CSP allowlist

**Files:**
- Modify: `resources/views/layouts/app.blade.php`
- Modify: `app/Http/Middleware/SecurityHeaders.php`
- Modify: `tests/Feature/SecurityHeadersTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: a global `Sortable` JS symbol available to Task 7's view, which already calls `new Sortable(...)` in its `x-init`.

- [ ] **Step 1: Write the failing test**

In `tests/Feature/SecurityHeadersTest.php`, add a new test method (after
`test_csp_allows_the_cdns_the_app_layout_actually_loads`):

```php
    public function test_csp_allows_the_sortablejs_cdn_the_dashboard_builder_loads(): void
    {
        $response = $this->get('/');

        $csp = $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString('https://cdn.jsdelivr.net', $csp);
    }
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Feature/SecurityHeadersTest.php`
Expected: FAIL — `cdn.jsdelivr.net` isn't in the CSP yet.

- [ ] **Step 3: Add Sortable.js to the layout**

In `resources/views/layouts/app.blade.php`, find:

```blade
    @livewireStyles
    {{-- No separate Alpine script here on purpose: @livewireScripts below
```

(the comment block added in `56002bf` explaining why Alpine isn't loaded
twice). Immediately after that whole comment block, before `</head>`, add:

```blade
    <script defer src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
```

- [ ] **Step 4: Add the CSP allowlist entry**

In `app/Http/Middleware/SecurityHeaders.php`, change:

```php
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://fonts.bunny.net https://cdn.tailwindcss.com https://unpkg.com",
```

to:

```php
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://fonts.bunny.net https://cdn.tailwindcss.com https://unpkg.com https://cdn.jsdelivr.net",
```

Add one line to the file's existing CSP doc comment (above the `$csp =
implode(...)` block, after the three numbered mismatches already documented
there):

```php
    // 4. script-src needs https://cdn.jsdelivr.net: the Custom Dashboards
    //    feature's drag-to-reorder loads Sortable.js from this CDN
    //    (resources/views/layouts/app.blade.php) -- without this, the
    //    dashboard builder's widget grid throws "ReferenceError: Sortable
    //    is not defined" the moment it has 1+ widgets to reorder.
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --compact tests/Feature/SecurityHeadersTest.php`
Expected: PASS, 5 tests, 0 failures.

- [ ] **Step 6: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `passed`.

- [ ] **Step 7: Commit**

```bash
git add resources/views/layouts/app.blade.php app/Http/Middleware/SecurityHeaders.php tests/Feature/SecurityHeadersTest.php
git commit -m "$(cat <<'EOF'
fix: load Sortable.js for the dashboard builder's drag-to-reorder

dashboard-builder-panel.blade.php already calls new Sortable(...) in
its x-init, but no Sortable.js script was ever loaded anywhere in the
app -- a live ReferenceError today, found while scoping the Custom
Dashboards spec. Loaded via CDN (this layout has no build pipeline;
Tailwind/Alpine are already loaded the same way), CSP allowlisted.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 7: Rewrite `dashboard-builder-panel.blade.php`

**Files:**
- Modify: `resources/views/livewire/analytics/dashboard-builder-panel.blade.php`
- Modify: `tests/Feature/Livewire/DashboardBuilderPanelTest.php` (extend)

**Interfaces:**
- Consumes: everything from Tasks 3–6 (`METRIC_WIDGET_TYPES`, `$widgetMetricDefinitionId`, `$newDashboardVisibility`, `metricDefinitions()`, the two widget partials, Sortable.js).
- Produces: the finished, dark-restyled, fully-functional builder UI — the last piece before it's reachable (Task 8).

- [ ] **Step 1: Write the failing test**

Extend `tests/Feature/Livewire/DashboardBuilderPanelTest.php` with one more
test, appended at the end of the class:

```php
    public function test_a_dashboard_with_one_of_every_widget_type_renders_without_error(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $dashboard = AnalyticsDashboard::factory()->create(['team_id' => $user->currentTeam->id, 'user_id' => $user->id]);
        $metric = MetricDefinition::factory()->create();

        foreach (DashboardBuilderPanel::WIDGET_TYPES as $type => $label) {
            $config = ['type' => $type];
            if (in_array($type, DashboardBuilderPanel::METRIC_WIDGET_TYPES, true)) {
                $config['metric_definition_id'] = $metric->id;
            }

            DashboardWidget::factory()->create([
                'analytics_dashboard_id' => $dashboard->id,
                'widget_type' => $type,
                'config' => $config,
            ]);
        }

        Livewire::actingAs($user)
            ->test(DashboardBuilderPanel::class)
            ->call('selectDashboard', $dashboard->id)
            ->assertOk()
            ->assertSee($dashboard->title);
    }
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Feature/Livewire/DashboardBuilderPanelTest.php --filter=test_a_dashboard_with_one_of_every_widget_type_renders_without_error`
Expected: FAIL — the current view still prints only `$widget->widget_type` as
a placeholder label; it doesn't yet `@switch` into any real content, so this
specific assertion may pass by accident today (`assertSee` just checks the
title renders) — the real value of this test is guarding against a
regression once Step 3 below wires in the `@switch`. Confirm actual output
before continuing; if it already passes, that's expected pre-Step-3 (the
title always rendered) and is not a plan defect.

- [ ] **Step 3: Rewrite the view**

Replace the full contents of
`resources/views/livewire/analytics/dashboard-builder-panel.blade.php`:

```blade
<div style="padding:1.75rem 2rem 2rem;">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
        <div>
            <p class="font-mono" style="font-size:0.65rem;letter-spacing:0.14em;text-transform:uppercase;color:var(--teal-soft);margin:0 0 0.35rem;">My Dashboards</p>
            <h3 class="font-display" style="font-size:1.1rem;font-weight:700;color:var(--paper);margin:0;">Dashboard Builder</h3>
            <p style="font-size:0.75rem;color:var(--mist);margin:0.3rem 0 0;">Compose custom intelligence views from widget building blocks</p>
        </div>
        <button wire:click="$toggle('showNewDashboard')" class="press" style="font-size:0.72rem;padding:0.4rem 0.85rem;background:{{ $showNewDashboard ? 'transparent' : 'var(--gold)' }};color:{{ $showNewDashboard ? 'var(--paper)' : 'var(--ink)' }};border:1px solid {{ $showNewDashboard ? 'var(--line)' : 'transparent' }};border-radius:0.4rem;font-weight:600;">
            {{ $showNewDashboard ? 'Cancel' : '+ New Dashboard' }}
        </button>
    </div>

    {{-- Create dashboard form --}}
    @if($showNewDashboard)
        <div class="mb-5 rounded-xl p-4" style="border:1px solid var(--line);background:var(--panel);">
            <form wire:submit="createDashboard" class="flex gap-3 items-end flex-wrap">
                <div class="flex-1" style="min-width:200px;">
                    <label class="font-mono" style="display:block;font-size:0.62rem;letter-spacing:0.08em;text-transform:uppercase;color:var(--mist);margin-bottom:0.35rem;">Dashboard name</label>
                    <input wire:model="newDashboardTitle" type="text" placeholder="e.g. Ops Overview" class="w-full rounded-lg px-3 py-2 text-sm focus:outline-none" style="background:var(--ink-soft);border:1px solid var(--line);color:var(--paper);" />
                    @error('newDashboardTitle') <span style="color:var(--danger);font-size:0.7rem;">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="font-mono" style="display:block;font-size:0.62rem;letter-spacing:0.08em;text-transform:uppercase;color:var(--mist);margin-bottom:0.35rem;">Visibility</label>
                    <select wire:model="newDashboardVisibility" class="rounded-lg px-3 py-2 text-sm" style="background:var(--ink-soft);border:1px solid var(--line);color:var(--paper);">
                        <option value="private">Private — only me</option>
                        <option value="team">Shared with team</option>
                    </select>
                </div>
                <button type="submit" class="press" style="background:var(--gold);color:var(--ink);padding:0.5rem 1.25rem;border-radius:0.4rem;font-size:0.8rem;font-weight:600;">Create</button>
            </form>
        </div>
    @endif

    <div class="flex" style="min-height:480px;border:1px solid var(--line);border-radius:12px;overflow:hidden;">
        {{-- Sidebar: dashboard list --}}
        <div style="width:224px;border-right:1px solid var(--line);overflow-y:auto;flex-shrink:0;">
            @if($this->dashboards->isEmpty())
                <p style="font-size:0.75rem;color:var(--mist);padding:1.5rem 1rem;text-align:center;">No dashboards yet. Create your first one above.</p>
            @else
                @foreach($this->dashboards as $dash)
                    <button
                        wire:click="selectDashboard({{ $dash->id }})"
                        class="press"
                        style="width:100%;text-align:left;padding:0.75rem 1rem;font-size:0.8rem;border-bottom:1px solid var(--line);background:{{ ($this->activeDashboard?->id === $dash->id) ? 'rgba(241,198,46,0.06)' : 'transparent' }};color:{{ ($this->activeDashboard?->id === $dash->id) ? 'var(--gold-soft)' : 'var(--paper)' }};"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span class="truncate" style="{{ ($this->activeDashboard?->id === $dash->id) ? 'font-weight:600;' : '' }}">{{ $dash->title }}</span>
                            @if($dash->is_default)
                                <span class="font-mono" style="font-size:0.58rem;background:rgba(43,182,183,0.1);color:var(--teal-soft);padding:0.1rem 0.4rem;border-radius:0.25rem;flex-shrink:0;">Default</span>
                            @endif
                        </div>
                        <p style="font-size:0.68rem;color:var(--mist);margin:0.2rem 0 0;">
                            {{ $dash->widgets()->count() }} widgets
                            @if($dash->visibility === 'team')
                                &middot; shared
                            @endif
                        </p>
                    </button>
                @endforeach
            @endif
        </div>

        {{-- Main canvas --}}
        <div class="flex-1 p-5 overflow-y-auto">
            @if(! $this->activeDashboard)
                <div class="flex items-center justify-center h-full">
                    <p style="font-size:0.85rem;color:var(--mist);">Select or create a dashboard to start building.</p>
                </div>
            @else
                {{-- Dashboard toolbar --}}
                <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
                    <div class="flex items-center gap-2">
                        <h4 class="font-display" style="font-size:0.95rem;font-weight:700;color:var(--paper);">{{ $this->activeDashboard->title }}</h4>
                        @if(! $this->activeDashboard->is_default)
                            <button wire:click="setDefault({{ $this->activeDashboard->id }})" style="font-size:0.68rem;color:var(--mist);background:none;border:none;cursor:pointer;">Set as my default</button>
                        @endif
                    </div>
                    <div class="flex gap-2">
                        <button wire:click="$toggle('showAddWidget')" class="press" style="font-size:0.72rem;padding:0.4rem 0.85rem;background:var(--gold);color:var(--ink);border-radius:0.4rem;font-weight:600;">
                            {{ $showAddWidget ? 'Cancel' : '+ Add Widget' }}
                        </button>
                        <button
                            wire:click="deleteDashboard({{ $this->activeDashboard->id }})"
                            wire:confirm="Delete '{{ $this->activeDashboard->title }}'? All widgets will be removed."
                            style="font-size:0.72rem;color:var(--mist);background:none;border:1px solid var(--line);padding:0.4rem 0.7rem;border-radius:0.4rem;cursor:pointer;"
                            onmouseover="this.style.color='var(--danger)'" onmouseout="this.style.color='var(--mist)'"
                        >Delete</button>
                    </div>
                </div>

                {{-- Add widget form --}}
                @if($showAddWidget)
                    <form wire:submit="addWidget" class="mb-4 rounded-xl p-4 grid grid-cols-2 gap-3" style="border:1px solid var(--teal-soft);background:var(--panel);">
                        <div>
                            <label class="font-mono" style="display:block;font-size:0.62rem;letter-spacing:0.08em;text-transform:uppercase;color:var(--mist);margin-bottom:0.35rem;">Widget type</label>
                            <select wire:model.live="widgetType" class="w-full rounded-lg px-3 py-2 text-sm" style="background:var(--ink-soft);border:1px solid var(--line);color:var(--paper);">
                                @foreach(\App\Livewire\Analytics\DashboardBuilderPanel::WIDGET_TYPES as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="font-mono" style="display:block;font-size:0.62rem;letter-spacing:0.08em;text-transform:uppercase;color:var(--mist);margin-bottom:0.35rem;">Widget title (optional)</label>
                            <input wire:model="widgetTitle" type="text" placeholder="Auto-filled from type" class="w-full rounded-lg px-3 py-2 text-sm focus:outline-none" style="background:var(--ink-soft);border:1px solid var(--line);color:var(--paper);" />
                        </div>
                        @if(in_array($widgetType, \App\Livewire\Analytics\DashboardBuilderPanel::METRIC_WIDGET_TYPES))
                            <div class="col-span-2">
                                <label class="font-mono" style="display:block;font-size:0.62rem;letter-spacing:0.08em;text-transform:uppercase;color:var(--mist);margin-bottom:0.35rem;">Metric</label>
                                <select wire:model="widgetMetricDefinitionId" class="w-full rounded-lg px-3 py-2 text-sm" style="background:var(--ink-soft);border:1px solid var(--line);color:var(--paper);">
                                    <option value="">Choose a metric&hellip;</option>
                                    @foreach($this->metricDefinitions as $definition)
                                        <option value="{{ $definition->id }}">{{ $definition->label }}</option>
                                    @endforeach
                                </select>
                                @error('widgetMetricDefinitionId') <span style="color:var(--danger);font-size:0.7rem;">{{ $message }}</span> @enderror
                            </div>
                        @endif
                        <div class="col-span-2">
                            <button type="submit" class="press" style="background:var(--gold);color:var(--ink);padding:0.5rem 1.25rem;border-radius:0.4rem;font-size:0.8rem;font-weight:600;">Add to Dashboard</button>
                        </div>
                    </form>
                @endif

                {{-- Widget grid --}}
                @if($this->widgets->isEmpty())
                    <div class="rounded-xl flex items-center justify-center" style="border:2px dashed var(--line);height:12rem;">
                        <p style="font-size:0.85rem;color:var(--mist);">Add your first widget above to start building.</p>
                    </div>
                @else
                    <div
                        class="grid grid-cols-1 lg:grid-cols-2 gap-4"
                        x-data="{ sortable: null }"
                        x-init="
                            sortable = new Sortable($el, {
                                animation: 150,
                                ghostClass: 'opacity-30',
                                onEnd: (evt) => {
                                    const ids = Array.from($el.querySelectorAll('[data-widget-id]'))
                                        .map(el => parseInt(el.dataset.widgetId));
                                    $wire.updatePositions(ids);
                                }
                            });
                        "
                    >
                        @foreach($this->widgets as $widget)
                            <div data-widget-id="{{ $widget->id }}" class="rounded-xl" style="border:1px solid var(--line);overflow:hidden;">
                                <div class="flex items-center justify-between cursor-grab active:cursor-grabbing" style="padding:0.6rem 0.9rem;border-bottom:1px solid var(--line);background:rgba(255,255,255,0.02);">
                                    <div class="flex items-center gap-2" style="min-width:0;">
                                        <span class="material-symbols-outlined" style="font-size:16px;color:var(--mist);">{{ match($widget->widget_type) {
                                            'metric_card' => 'speed',
                                            'chart' => 'show_chart',
                                            'alert_feed' => 'warning',
                                            'recommendation_feed' => 'lightbulb',
                                            'insight_feed' => 'insights',
                                            'dna_snapshot' => 'biotech',
                                            'briefing_summary' => 'summarize',
                                            default => 'widgets',
                                        } }}</span>
                                        <p class="truncate" style="font-size:0.75rem;font-weight:600;color:var(--paper);margin:0;">{{ $widget->title ?? 'Untitled Widget' }}</p>
                                    </div>
                                    <button
                                        wire:click="removeWidget({{ $widget->id }})"
                                        style="color:var(--mist);background:none;border:none;cursor:pointer;font-size:0.75rem;flex-shrink:0;"
                                        onmouseover="this.style.color='var(--danger)'" onmouseout="this.style.color='var(--mist)'"
                                    >&#10005;</button>
                                </div>
                                <div style="padding:0.75rem 0.9rem;max-height:420px;overflow-y:auto;">
                                    @switch($widget->widget_type)
                                        @case('alert_feed')
                                            <livewire:analytics.alerts-panel :key="'widget-'.$widget->id" />
                                            @break
                                        @case('recommendation_feed')
                                            <livewire:analytics.recommendations-panel :key="'widget-'.$widget->id" />
                                            @break
                                        @case('insight_feed')
                                            <livewire:analytics.cross-platform-insight-panel :key="'widget-'.$widget->id" />
                                            @break
                                        @case('dna_snapshot')
                                            <livewire:analytics.business-dna-panel :key="'widget-'.$widget->id" />
                                            @break
                                        @case('briefing_summary')
                                            <livewire:analytics.executive-briefing-panel :key="'widget-'.$widget->id" />
                                            @break
                                        @case('metric_card')
                                            @include('livewire.analytics.widgets.metric-card', ['widget' => $widget])
                                            @break
                                        @case('chart')
                                            @include('livewire.analytics.widgets.metric-chart', ['widget' => $widget])
                                            @break
                                    @endswitch
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>
```

Two deliberate deviations from the pre-existing view, beyond the redesign
itself:
1. `wire:model.live="widgetType"` (was plain `wire:model`) — the metric
   picker's `@if(in_array($widgetType, ...))` needs to react the instant the
   dropdown changes, not wait for some other action to trigger a re-render.
2. The widget tile's content wrapper (`max-height:420px;overflow-y:auto;`)
   per Global Constraints — five of the seven widget types embed full,
   potentially-tall panels.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Livewire/DashboardBuilderPanelTest.php`
Expected: PASS, 11 tests, 0 failures.

- [ ] **Step 5: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `passed`.

- [ ] **Step 6: Commit**

```bash
git add resources/views/livewire/analytics/dashboard-builder-panel.blade.php \
  tests/Feature/Livewire/DashboardBuilderPanelTest.php
git commit -m "$(cat <<'EOF'
feat: finish the dashboard builder view -- real widgets, dark restyle

Replaces the placeholder widget tiles (previously just printed their
own widget_type string) with real rendering for all 7 types, adds the
visibility selector and metric picker forms, simplifies the grid to a
responsive 1-2 column stack ordered by row (col/width/height were
computed but never actually used by the old fixed grid-cols-3), and
restyles the whole panel to match the ink/teal/gold system from
56002bf. Each tile is capped at 420px with its own scroll, since five
of the seven widget types embed full existing panels.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 8: Route, page, and navigation

**Files:**
- Modify: `routes/web.php`
- Create: `resources/views/dashboards.blade.php`
- Modify: `resources/views/layouts/app.blade.php`
- Test: `tests/Feature/DashboardsPageTest.php`

**Interfaces:**
- Consumes: `livewire.analytics.dashboard-builder-panel` (Task 7).
- Produces: `dashboards.index` named route — the feature is reachable from here on.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/DashboardsPageTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_team_member_can_view_the_dashboards_page(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user)
            ->get(route('dashboards.index'))
            ->assertOk()
            ->assertSee('My Dashboards');
    }

    public function test_a_user_with_no_current_team_is_redirected_to_team_creation(): void
    {
        $user = User::factory()->create(['current_team_id' => null]);

        $this->actingAs($user)
            ->get(route('dashboards.index'))
            ->assertRedirect(route('teams.create'));
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get(route('dashboards.index'))
            ->assertRedirect(route('login'));
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact tests/Feature/DashboardsPageTest.php`
Expected: FAIL/ERROR — the `dashboards.index` route doesn't exist yet
(`route()` throws `RouteNotFoundException`).

- [ ] **Step 3: Add the route**

In `routes/web.php`, immediately after the existing `/dashboard` route's
closing `})->name('dashboard');` and before the `/reports/{id}/download`
route, add:

```php
    Route::get('/dashboards', function () {
        // Same reachable-null case as /dashboard above.
        if (! Auth::user()->currentTeam) {
            return redirect()->route('teams.create');
        }

        return view('dashboards');
    })->name('dashboards.index');

```

- [ ] **Step 4: Create the page view**

Create `resources/views/dashboards.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <p class="font-mono" style="font-size:0.7rem;letter-spacing:0.18em;text-transform:uppercase;color:var(--teal-soft);margin:0 0 0.6rem;">My Dashboards</p>
        <h1 class="font-display" style="font-size:1.75rem;font-weight:700;color:var(--paper);letter-spacing:-0.01em;margin:0;">Custom Intelligence Views</h1>
    </x-slot>

    <div style="padding:2rem 2.5rem 4rem;">
        <div style="max-width:1180px;margin:0 auto;border:1px solid var(--line);border-radius:14px;overflow:hidden;background:var(--ink-soft);">
            <livewire:analytics.dashboard-builder-panel />
        </div>
    </div>
</x-app-layout>
```

Same hairline-container treatment as `dashboard.blade.php` from `56002bf`.

- [ ] **Step 5: Add the nav link**

In `resources/views/layouts/app.blade.php`, find the existing sidebar `<nav>`
block:

```blade
        <nav style="flex:1;display:flex;flex-direction:column;gap:0.15rem;">
            <a href="{{ route('dashboard') }}" class="sl {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <span class="material-symbols-outlined" style="font-size:18px;">dashboard</span>
                <span>Intelligence Overview</span>
            </a>
        </nav>
```

Add a second link inside the same `<nav>`, right after the first:

```blade
        <nav style="flex:1;display:flex;flex-direction:column;gap:0.15rem;">
            <a href="{{ route('dashboard') }}" class="sl {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <span class="material-symbols-outlined" style="font-size:18px;">dashboard</span>
                <span>Intelligence Overview</span>
            </a>
            <a href="{{ route('dashboards.index') }}" class="sl {{ request()->routeIs('dashboards.index') ? 'active' : '' }}">
                <span class="material-symbols-outlined" style="font-size:18px;">dashboard_customize</span>
                <span>My Dashboards</span>
            </a>
        </nav>
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test --compact tests/Feature/DashboardsPageTest.php`
Expected: PASS, 3 tests, 0 failures.

- [ ] **Step 7: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `passed`.

- [ ] **Step 8: Commit**

```bash
git add routes/web.php resources/views/dashboards.blade.php resources/views/layouts/app.blade.php \
  tests/Feature/DashboardsPageTest.php
git commit -m "$(cat <<'EOF'
feat: make Custom Dashboards reachable at /dashboards

New route + page wrapping DashboardBuilderPanel (finished in the
previous four commits), plus a second sidebar nav entry below
Intelligence Overview. /dashboard itself is untouched -- this is a
separate, opt-in page, not a replacement.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 9: Full regression + browser verification

**Files:** none (verification only).

**Interfaces:**
- Consumes: everything from Tasks 1–8.
- Produces: nothing new — confirms the whole feature works together.

- [ ] **Step 1: Run the full test suite**

Run: `php artisan test --compact`
Expected: PASS, 0 failures. (Baseline before this plan: 504 passed / 7
skipped, per `56002bf`'s own verification — expect that plus every new test
added across Tasks 1–8: 3 + 8 + 10 + 3 + 4 + 1 (extends existing file) + 1
(extends existing file) + 3 = 33 new tests, so roughly 537 passed / 7
skipped. If the actual new-test count differs from this add-up, that's fine
as long as the total is 0 failures — this estimate exists to catch a test
silently not running, not to gate on an exact number.)

- [ ] **Step 2: Run Pint across the whole plan's changes**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `passed`.

- [ ] **Step 3: Browser verification**

Start the `dot-analytics` dev server (per this repo's `.claude/launch.json`
entry) and, as an authenticated team member:
1. Navigate to `/dashboards` — confirm it loads with the dark ink/teal/gold
   styling, matching `/dashboard`.
2. Create a private dashboard, add one of each widget type (a `metric_card`
   and `chart` need a `MetricDefinition` to exist — the seeded catalog
   already provides 55+, per `wiki.md`).
3. Confirm each widget tile renders real content (or a clear empty state for
   `metric_card`/`chart`, since `ComputedMetric` is empty in this
   environment) — not a blank tile, not a JS console error.
4. Drag a widget to reorder it (confirms Sortable.js loads and
   `updatePositions` persists — reload the page and confirm the new order
   stuck).
5. Create a second, team-visibility dashboard; confirm it's marked "shared"
   in the sidebar list.
6. Check the browser console for errors (matching the verification pattern
   from `56002bf`'s own browser pass) — zero expected.

- [ ] **Step 4: Finish the branch**

Announce: "I'm using the finishing-a-development-branch skill to complete
this work." Follow that skill: verify tests (Step 1 above already confirmed
green), detect environment, present the standard menu, execute the chosen
option.

---

## Self-Review Notes

- **Spec coverage:** §1 (visibility + per-owner default) → Task 1 + Task 3.
  §2 (policy) → Task 2, consumed by Task 3. §3 (widget rendering: 5 reused
  panels + 2 new) → Task 3 (config plumbing), Task 4 (`metric_card`), Task 5
  (`chart`), Task 7 (wiring the 5 reused panels + both new partials into the
  view). §4 (layout + Sortable.js) → Task 6 (library + CSP) + Task 7 (grid
  simplification). §5 (nav/routing) → Task 8. Testing Strategy → present
  throughout; the spec's `AnalyticsDashboardPolicyTest` item is Task 2, its
  `DashboardBuilderPanelTest` item is Task 3 (extended in Task 7), its widget
  smoke tests are Tasks 4–5, its "two new factories" item is Task 1 (plus
  `ComputedMetricFactory` in Task 4, needed once the spec's chosen widget
  types were finalized). Non-Goals (no metric pipeline, no free-form grid, no
  JS charting library, no per-viewer default on unowned dashboards, no
  `/dashboard` replacement) — no task touches any of those.
- **Refinements found during planning, not in the original spec text:**
  (1) `AnalyticsDashboardPolicy::update()`/`delete()` need a current-team
  match guard, not just `user_id` match — Task 2 Step 1's last test and Step
  3's policy code. (2) `removeWidget()` must explicitly handle
  `$widget->dashboard` resolving to `null` (cross-team widget) before
  authorizing, to avoid a raw `TypeError` — Task 3 Step 3's numbered note.
  Both documented inline at the point they're introduced.
- **Placeholder scan:** none found. Every step has real, complete code.
- **Type consistency:** `DashboardWidget.config` is consistently a plain
  array with a `metric_definition_id` int key across Task 3 (writer),
  Task 4/5 (readers), and every test. `AnalyticsDashboard.visibility` is
  consistently the string `'private'`/`'team'` everywhere it appears — no
  enum, no boolean, matching Task 1's migration and every later reference.
  `METRIC_WIDGET_TYPES` (Task 3) is referenced identically by name in Task 7's
  view and nowhere redefined or duplicated as a literal array.
