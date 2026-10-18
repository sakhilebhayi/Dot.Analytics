<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Laravel\Scout\Builder as ScoutBuilder;
use Laravel\Scout\Searchable;

/**
 * Scout's Searchable for models that belong to one company (team), made fail-closed.
 *
 * Scout's database engine builds its query from the bare model. The only thing keeping
 * another company's rows out of that query is the HasTeamScope global scope, and that exists
 * only while a user is signed in with a current team: in a queue worker, a console command
 * or a test with nobody signed in, `Model::search('x')->get()` would search every company.
 *
 * So a search here must say which team it is for, `Model::search($q)->where('team_id', $id)`,
 * and the database engine's query is constrained to exactly that team before any text is
 * matched. A search that does not (or names something that is not a team id) matches nothing.
 * The other engines Scout supports honour the same `where`, but only the database engine is
 * supported here: nothing else is configured and none is told what is searchable.
 *
 * Each model still declares its own toSearchableArray(): that list is the whole set of
 * columns text is matched against, so it never inherits toArray() and with it a secret.
 * Go through App\Services\Search\GlobalSearch rather than calling search() directly.
 */
trait SearchesWithinTeam
{
    use Searchable;

    /** @return Builder<static> */
    public function newScoutQuery(?ScoutBuilder $builder = null): Builder
    {
        $teamId = $builder?->wheres['team_id'] ?? null;

        if (! is_int($teamId)) {
            return $this->newQuery()->whereRaw('1 = 0');
        }

        return $this->newQuery()->where($this->qualifyColumn('team_id'), $teamId);
    }
}
