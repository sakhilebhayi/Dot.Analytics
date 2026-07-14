# Tenant Isolation — Implementation Guide

**Scorecard domain:** Multi-Tenancy Isolation (currently 55/100)
**Target:** 85/100
**Why it matters:** A single missing `where('team_id', ...)` in any query leaks
data between organisations. This guide makes isolation automatic and auditable.

---

## Current State

All tenant isolation is **manual** — every query must explicitly include
`where('team_id', Auth::user()->currentTeam->id)`. This works today but is
fragile: one forgotten scope in a future feature = data breach.

---

## Fix 1 — Global Eloquent Scope Trait (Highest Priority)

Create `app/Models/Concerns/BelongsToTeam.php`:

```php
<?php

namespace App\Models\Concerns;

use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait BelongsToTeam
{
    public static function bootBelongsToTeam(): void
    {
        // Automatically scope all queries to the current team
        static::addGlobalScope('team', function (Builder $builder) {
            if (Auth::check() && Auth::user()->currentTeam) {
                $builder->where(
                    (new static)->getTable() . '.team_id',
                    Auth::user()->currentTeam->id,
                );
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
```

Apply to every team-owned model:

```php
// In each model:
use App\Models\Concerns\BelongsToTeam;

class CrossPlatformInsight extends Model
{
    use BelongsToTeam;
    // ...
}
```

**Models to update:**
- `DataSource`
- `CrossPlatformInsight`
- `AnalyticsAlert`
- `Recommendation`
- `BusinessDnaProfile`
- `AnalyticsDashboard`
- `DataPipeline`
- `DataConnector`
- `ExecutiveBriefing`
- `IntelligenceEngineRun`
- `AnalyticsReport`

**When bypassing is needed** (e.g. admin console, seeder, job processing):

```php
// Disable the scope explicitly — intention is documented
DataSource::withoutGlobalScope('team')->where('platform', 'dot.fleet')->get();
```

---

## Fix 2 — Team-Aware Cache

Create `app/Services/TeamAwareCache.php`:

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class TeamAwareCache
{
    public function __construct(private readonly int $teamId) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return Cache::get($this->key($key), $default);
    }

    public function put(string $key, mixed $value, int $ttlSeconds = 300): void
    {
        Cache::put($this->key($key), $value, $ttlSeconds);
    }

    public function forget(string $key): void
    {
        Cache::forget($this->key($key));
    }

    public function remember(string $key, int $ttlSeconds, callable $callback): mixed
    {
        return Cache::remember($this->key($key), $ttlSeconds, $callback);
    }

    /** Flush all cache keys belonging to this team. */
    public function flush(): void
    {
        Cache::forget("team:{$this->teamId}:*");
    }

    private function key(string $key): string
    {
        return "team:{$this->teamId}:{$key}";
    }
}
```

Usage in services:

```php
$cache = new TeamAwareCache($team->id);
$result = $cache->remember('active-engines', 300, fn () => $service->getActiveEngines(...));
```

---

## Fix 3 — Queue Job Tenant Validation Middleware

Create `app/Jobs/Middleware/EnsureTeamExists.php`:

```php
<?php

namespace App\Jobs\Middleware;

use App\Models\Team;
use Illuminate\Support\Facades\Log;

class EnsureTeamExists
{
    public function handle(object $job, callable $next): void
    {
        if (! isset($job->teamId)) {
            $next($job);
            return;
        }

        if (! Team::find($job->teamId)) {
            Log::warning('Job discarded — team no longer exists', [
                'job'     => get_class($job),
                'team_id' => $job->teamId,
            ]);
            $job->delete(); // Discard without retry
            return;
        }

        $next($job);
    }
}
```

Apply to all analytics jobs:

```php
public function middleware(): array
{
    return [new EnsureTeamExists()];
}
```

---

## Testing

Add to your test suite after implementing:

```php
public function test_global_scope_prevents_cross_tenant_access(): void
{
    $teamA = User::factory()->withPersonalTeam()->create()->currentTeam;
    $teamB = User::factory()->withPersonalTeam()->create()->currentTeam;

    CrossPlatformInsight::factory()->create(['team_id' => $teamA->id]);

    $this->actingAs(User::factory()->withPersonalTeam()->create());

    // Should return 0 — global scope applies automatically
    $this->assertEquals(0, CrossPlatformInsight::count());
}
```

---

## Checklist

- [ ] `BelongsToTeam` trait created
- [ ] Trait applied to all 11 team-owned models
- [ ] `TeamAwareCache` service created and registered
- [ ] `EnsureTeamExists` job middleware created and applied to all jobs
- [ ] Tests written and passing
- [ ] Scorecard updated
