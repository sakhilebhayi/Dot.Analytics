<?php

namespace App\Services\Search;

use App\Models\AnalyticsAlert;
use App\Models\AnalyticsDashboard;
use App\Models\AnalyticsReport;
use App\Models\CrossPlatformInsight;
use App\Models\IntelligenceNode;
use App\Models\Recommendation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Finds a team's own content across the six things this app stores that people look for.
 *
 * Read-only, so it is open to every team role, the read-only viewer included. It searches the
 * team the user is currently working in, and only if they really belong to it; the team is an
 * explicit constraint on every query (see SearchesWithinTeam), never inferred from whoever
 * happens to be signed in. Dashboards are team-wide in this app, so there is nothing to
 * filter per member.
 */
class GlobalSearch
{
    /** Fewer characters than this match far too much to be a search. */
    public const MIN_LENGTH = 2;

    /** Anything longer is cut; no title or narrative anyone searches for needs more. */
    public const MAX_LENGTH = 100;

    /** Per type. */
    public const LIMIT = 10;

    /** @var array<string, class-string<Model>> */
    public const TYPES = [
        'nodes' => IntelligenceNode::class,
        'insights' => CrossPlatformInsight::class,
        'alerts' => AnalyticsAlert::class,
        'recommendations' => Recommendation::class,
        'reports' => AnalyticsReport::class,
        'dashboards' => AnalyticsDashboard::class,
    ];

    /**
     * @return array<string, Collection<int, Model>> matches per type, newest first
     */
    public function search(User $user, string $query): array
    {
        $results = [];
        foreach (array_keys(self::TYPES) as $type) {
            $results[$type] = $this->in($type, $user, $query);
        }

        return $results;
    }

    /** @return Collection<int, Model> */
    public function in(string $type, User $user, string $query): Collection
    {
        $model = self::TYPES[$type] ?? throw new \InvalidArgumentException("Unknown search type [{$type}].");

        $term = $this->normalise($query);
        $team = $user->currentTeam;

        if ($term === null || $team === null || ! $user->belongsToTeam($team)) {
            return collect();
        }

        return $model::search($term)
            ->where('team_id', $team->id)
            ->take(self::LIMIT)
            ->get();
    }

    /** The text to search for, or null when there is nothing worth searching. */
    public function normalise(string $query): ?string
    {
        $term = mb_substr(trim($query), 0, self::MAX_LENGTH);

        return mb_strlen($term) >= self::MIN_LENGTH ? $term : null;
    }
}
