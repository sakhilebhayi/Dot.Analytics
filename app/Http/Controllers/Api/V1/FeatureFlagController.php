<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\FeatureFlag;
use App\Services\FeatureFlagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Feature Flag Controller — v1
 *
 * Allows authorised administrators to manage feature flags at runtime
 * without deploying code. Used for gradual rollouts and A/B testing.
 */
class FeatureFlagController extends BaseApiController
{
    public function __construct(
        private readonly FeatureFlagService $flagService,
    ) {}

    /**
     * GET /api/v1/feature-flags
     * List all feature flags with their current state.
     */
    public function index(): JsonResponse
    {
        $flags = $this->flagService->all()->map(fn ($f) => [
            'key'                => $f->key,
            'name'               => $f->name,
            'description'        => $f->description,
            'enabled_globally'   => $f->enabled_globally,
            'enabled_for_teams'  => $f->enabled_for_teams,
            'rollout_percentage' => $f->rollout_percentage,
            'environment'        => $f->environment,
        ]);

        return $this->success($flags);
    }

    /**
     * GET /api/v1/feature-flags/check/{key}
     * Check if a flag is enabled for the authenticated user.
     */
    public function check(string $key): JsonResponse
    {
        return $this->success([
            'key'     => $key,
            'enabled' => $this->flagService->isEnabled($key, Auth::user()),
        ]);
    }

    /**
     * POST /api/v1/feature-flags
     * Create a new feature flag. Admin only.
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('manage-platforms');

        $validated = $request->validate([
            'key'                => 'required|string|max:100|unique:feature_flags,key',
            'name'               => 'required|string|max:120',
            'description'        => 'nullable|string|max:500',
            'enabled_globally'   => 'boolean',
            'enabled_for_teams'  => 'nullable|array',
            'rollout_percentage' => 'numeric|min:0|max:100',
            'environment'        => 'in:all,production,local',
        ]);

        $flag = FeatureFlag::create($validated);

        return $this->success($flag, 'Feature flag created.', 201);
    }

    /**
     * PATCH /api/v1/feature-flags/{key}/enable
     */
    public function enable(string $key): JsonResponse
    {
        Gate::authorize('manage-platforms');
        $this->flagService->enable($key);
        return $this->success(['key' => $key, 'enabled' => true]);
    }

    /**
     * PATCH /api/v1/feature-flags/{key}/disable
     */
    public function disable(string $key): JsonResponse
    {
        Gate::authorize('manage-platforms');
        $this->flagService->disable($key);
        return $this->success(['key' => $key, 'enabled' => false]);
    }

    /**
     * PATCH /api/v1/feature-flags/{key}/rollout
     * Body: { percentage: 50 }
     */
    public function rollout(Request $request, string $key): JsonResponse
    {
        Gate::authorize('manage-platforms');

        $validated = $request->validate(['percentage' => 'required|numeric|min:0|max:100']);
        $this->flagService->setRollout($key, $validated['percentage']);

        return $this->success(['key' => $key, 'rollout_percentage' => $validated['percentage']]);
    }
}
