# Authorization — Missing Policies & IDOR Prevention

**Scorecard domain:** Authorization (currently 74/100)
**Target:** 90/100

---

## Gap: Three Models Have No Policy

`AnalyticsDashboard`, `DataPipeline`, and `FeatureFlag` allow any authenticated
user to manipulate records belonging to other teams if they know the numeric ID.

---

## Fix 1 — AnalyticsDashboardPolicy

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
        return $user->currentTeam?->id === $dashboard->team_id;
    }

    public function create(User $user): bool
    {
        return $user->currentTeam !== null;
    }

    public function update(User $user, AnalyticsDashboard $dashboard): bool
    {
        return $user->currentTeam?->id === $dashboard->team_id;
    }

    public function delete(User $user, AnalyticsDashboard $dashboard): bool
    {
        return $user->currentTeam?->user_id === $user->id
            && $user->currentTeam?->id === $dashboard->team_id;
    }
}
```

## Fix 2 — DataPipelinePolicy

```php
// Same pattern — scope to team_id, delete requires owner
class DataPipelinePolicy
{
    use HandlesAuthorization;

    public function view(User $user, DataPipeline $pipeline): bool
    {
        return $user->currentTeam?->id === $pipeline->team_id;
    }

    public function create(User $user): bool
    {
        return $user->can('manage-connectors');
    }

    public function update(User $user, DataPipeline $pipeline): bool
    {
        return $user->currentTeam?->id === $pipeline->team_id
            && $user->can('manage-connectors');
    }

    public function delete(User $user, DataPipeline $pipeline): bool
    {
        return $user->currentTeam?->user_id === $user->id
            && $user->currentTeam?->id === $pipeline->team_id;
    }
}
```

## Fix 3 — FeatureFlagPolicy

```php
// Feature flags are global — restricted to platform admins
class FeatureFlagPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->currentTeam !== null; // All members can view
    }

    public function create(User $user): bool
    {
        return $user->can('manage-platforms'); // Admins only
    }

    public function update(User $user, FeatureFlag $flag): bool
    {
        return $user->can('manage-platforms');
    }

    public function delete(User $user, FeatureFlag $flag): bool
    {
        return $user->currentTeam?->user_id === $user->id; // Owner only
    }
}
```

---

## Register Policies

Add to `app/Providers/AppServiceProvider.php` `boot()`:

```php
use Illuminate\Support\Facades\Gate;
use App\Models\AnalyticsDashboard;
use App\Models\DataPipeline;
use App\Models\FeatureFlag;
use App\Policies\AnalyticsDashboardPolicy;
use App\Policies\DataPipelinePolicy;
use App\Policies\FeatureFlagPolicy;

Gate::policy(AnalyticsDashboard::class, AnalyticsDashboardPolicy::class);
Gate::policy(DataPipeline::class,       DataPipelinePolicy::class);
Gate::policy(FeatureFlag::class,        FeatureFlagPolicy::class);
```

---

## Apply in Livewire Components

```php
// DashboardBuilderPanel.php
public function deleteDashboard(int $id): void
{
    $dashboard = AnalyticsDashboard::findOrFail($id);
    $this->authorize('delete', $dashboard); // Add this line
    $dashboard->delete();
}
```

---

## IDOR Prevention Checklist

Every controller and Livewire method that accepts a model ID must:

1. Fetch with team scope: `AnalyticsDashboard::where('team_id', $team->id)->findOrFail($id)`  
   **OR** use the global scope from `tenant-isolation.md` (preferred)
2. Call `$this->authorize('update', $model)` before mutating
3. Never expose raw IDs in URLs without ownership validation

---

## Testing Template

```php
public function test_user_cannot_delete_another_teams_dashboard(): void
{
    $ownerA = User::factory()->withPersonalTeam()->create();
    $ownerB = User::factory()->withPersonalTeam()->create();

    $dashboard = AnalyticsDashboard::create([
        'team_id' => $ownerA->currentTeam->id,
        'user_id' => $ownerA->id,
        'title'   => 'Protected',
    ]);

    $this->actingAs($ownerB);
    $response = $this->deleteJson("/api/v1/dashboards/{$dashboard->id}");
    $response->assertForbidden();
}
```

---

## Checklist

- [ ] `AnalyticsDashboardPolicy` created and registered
- [ ] `DataPipelinePolicy` created and registered
- [ ] `FeatureFlagPolicy` created and registered
- [ ] `$this->authorize()` added to all Livewire methods that mutate these models
- [ ] IDOR tests written for each policy
- [ ] Scorecard updated
