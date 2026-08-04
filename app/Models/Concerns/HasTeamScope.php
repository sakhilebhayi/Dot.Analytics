<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Dot.Analytics is Jetstream-Teams multi-tenant, not single-user (see
 * DataSourcePolicy / CrossPlatformInsightPolicy). Every model that owns a
 * team_id column applies this trait so a query against it is scoped to the
 * authenticated user's current team by default, the same way Dot.Mines'
 * HasTeamFilters scopes every tenant-owned model to the current team -- the
 * goal is that a forgotten where('team_id', ...) call in a future
 * controller or Livewire component can no longer leak another team's rows,
 * because the model itself never returns unscoped results while a user is
 * authenticated with a current team.
 *
 * mass-assignment still sets team_id explicitly at create time (see each
 * controller/component's store()/create() call); this scope only governs
 * reads.
 */
trait HasTeamScope
{
    protected static function bootHasTeamScope(): void
    {
        static::addGlobalScope('team', function (Builder $builder): void {
            if (Auth::check() && Auth::user()->currentTeam) {
                $builder->where($builder->getModel()->getTable().'.team_id', Auth::user()->currentTeam->id);
            }
        });
    }
}
