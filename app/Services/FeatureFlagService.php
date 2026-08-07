<?php

namespace App\Services;

use App\Models\FeatureFlag;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Feature Flag Service
 *
 * Evaluates whether a feature is enabled for a given user and team.
 * Supports:
 *  - Global on/off toggle
 *  - Per-team enablement
 *  - Per-user enablement
 *  - Gradual rollout by percentage (deterministic via user ID hash)
 *  - Environment-scoped flags
 *
 * Results are cached per-flag for 60 seconds to reduce database load.
 */
class FeatureFlagService
{
    private const CACHE_TTL = 60;

    /**
     * Check if a feature is enabled for the given user.
     * Pass null for user to check global state only.
     */
    public function isEnabled(string $key, ?User $user = null): bool
    {
        $flag = $this->getFlag($key);

        if (! $flag) {
            return false;
        }

        // Environment check
        if ($flag->environment !== 'all' && $flag->environment !== config('app.env')) {
            return false;
        }

        // Global enable
        if ($flag->enabled_globally) {
            return true;
        }

        if (! $user) {
            return false;
        }

        // Per-user enable
        if (! empty($flag->enabled_for_users) && in_array($user->id, $flag->enabled_for_users, true)) {
            return true;
        }

        // Per-team enable
        $teamId = $user->currentTeam?->id;
        if ($teamId && ! empty($flag->enabled_for_teams) && in_array($teamId, $flag->enabled_for_teams, true)) {
            return true;
        }

        // Gradual rollout — deterministic by user ID so the same user always gets the same result
        if ($flag->rollout_percentage > 0) {
            $bucket = ($user->id % 100) + 1; // 1-100

            return $bucket <= $flag->rollout_percentage;
        }

        return false;
    }

    /**
     * Enable a flag globally.
     */
    public function enable(string $key): void
    {
        FeatureFlag::where('key', $key)->update(['enabled_globally' => true]);
        $this->bust($key);
    }

    /**
     * Disable a flag globally.
     */
    public function disable(string $key): void
    {
        FeatureFlag::where('key', $key)->update(['enabled_globally' => false]);
        $this->bust($key);
    }

    /**
     * Set the rollout percentage for gradual deployment.
     */
    public function setRollout(string $key, float $percentage): void
    {
        FeatureFlag::where('key', $key)->update(['rollout_percentage' => min(100, max(0, $percentage))]);
        $this->bust($key);
    }

    /**
     * Return all flags with their current state.
     */
    public function all(): Collection
    {
        return FeatureFlag::orderBy('key')->get();
    }

    private function getFlag(string $key): ?FeatureFlag
    {
        return Cache::remember("feature_flag:{$key}", self::CACHE_TTL, fn () => FeatureFlag::where('key', $key)->first()
        );
    }

    private function bust(string $key): void
    {
        Cache::forget("feature_flag:{$key}");
    }
}
