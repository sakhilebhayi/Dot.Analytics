<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\FeatureFlagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Feature Flag Controller — v1
 *
 * Allows platform operators to manage feature flags at runtime
 * without deploying code. Used for gradual rollouts and A/B testing.
 */
class FeatureFlagController extends BaseApiController
{
    public function __construct(
        private readonly FeatureFlagService $flagService,
    ) {}

    /**
     * GET /api/v1/feature-flags
     * List feature flags.
     *
     * Flags are global, so the raw targeting config (`enabled_for_teams` /
     * `enabled_for_users`, which reference OTHER tenants' ids, plus rollout
     * numbers) is only returned to platform operators. Everyone else gets just
     * each flag's effective state for themselves.
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();
        $isOperator = Gate::allows('manage-feature-flags');

        $flags = $this->flagService->all()->map(function ($f) use ($isOperator, $user) {
            if ($isOperator) {
                return [
                    'key' => $f->key,
                    'name' => $f->name,
                    'description' => $f->description,
                    'enabled_globally' => $f->enabled_globally,
                    'rollout_percentage' => $f->rollout_percentage,
                    'environment' => $f->environment,
                    'enabled_for_teams' => $f->enabled_for_teams,
                    'enabled_for_users' => $f->enabled_for_users,
                ];
            }

            return [
                'key' => $f->key,
                'name' => $f->name,
                'description' => $f->description,
                'enabled' => $this->flagService->isEnabled($f->key, $user),
            ];
        });

        return $this->success($flags);
    }

    /**
     * GET /api/v1/feature-flags/check/{key}
     * Check if a flag is enabled for the authenticated user.
     */
    public function check(string $key): JsonResponse
    {
        return $this->success([
            'key' => $key,
            'enabled' => $this->flagService->isEnabled($key, Auth::user()),
        ]);
    }

    /**
     * POST /api/v1/feature-flags
     * Create a new feature flag. Platform operators only.
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('manage-feature-flags');

        $validated = $request->validate([
            'key' => 'required|string|max:100|unique:feature_flags,key',
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:500',
            'enabled_globally' => 'boolean',
            'enabled_for_teams' => 'nullable|array',
            'rollout_percentage' => 'numeric|min:0|max:100',
            'environment' => 'in:all,production,local',
        ]);

        $flag = $this->flagService->create($validated, Auth::user());

        return $this->success($flag, 'Feature flag created.', 201);
    }

    /**
     * PATCH /api/v1/feature-flags/{key}/enable
     */
    public function enable(string $key): JsonResponse
    {
        Gate::authorize('manage-feature-flags');
        $this->flagService->enable($key, Auth::user());

        return $this->success(['key' => $key, 'enabled' => true]);
    }

    /**
     * PATCH /api/v1/feature-flags/{key}/disable
     */
    public function disable(string $key): JsonResponse
    {
        Gate::authorize('manage-feature-flags');
        $this->flagService->disable($key, Auth::user());

        return $this->success(['key' => $key, 'enabled' => false]);
    }

    /**
     * PATCH /api/v1/feature-flags/{key}/rollout
     * Body: { percentage: 50 }
     */
    public function rollout(Request $request, string $key): JsonResponse
    {
        Gate::authorize('manage-feature-flags');

        $validated = $request->validate(['percentage' => 'required|numeric|min:0|max:100']);
        $this->flagService->setRollout($key, $validated['percentage'], Auth::user());

        return $this->success(['key' => $key, 'rollout_percentage' => $validated['percentage']]);
    }
}
