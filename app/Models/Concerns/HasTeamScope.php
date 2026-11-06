<?php

namespace App\Models\Concerns;

use App\Support\TeamContext;
use Illuminate\Database\Eloquent\Builder;

/**
 * Every model that owns a team_id applies this trait, so a forgotten where('team_id', ...) cannot leak another
 * company's rows. Mass assignment still sets team_id explicitly at create time; this scope only governs reads.
 *
 * Inside a routed request it fails closed: no signed-in person, no current team, or a current team they do not belong
 * to reads nothing. Public pages run with exactly that context, so they can never reach private rows, even by mistake.
 * Scheduler, queue and console work has no route; services there name the team themselves.
 */
trait HasTeamScope
{
    protected static function bootHasTeamScope(): void
    {
        static::addGlobalScope('team', function (Builder $builder): void {
            if (TeamContext::inSystem()) {
                return;
            }

            $team = TeamContext::id();

            if ($team !== null) {
                $builder->where($builder->getModel()->getTable().'.team_id', $team);
            } elseif (app()->bound('request') && request()->route() !== null) {
                $builder->whereRaw('1 = 0');
            }
        });
    }
}
