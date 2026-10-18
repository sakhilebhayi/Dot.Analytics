<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Analytics\ConnectPlatformAction;
use App\Models\DataSource;
use App\Services\IntelligenceEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Platform API — v1
 *
 * Manage platform connections via the REST API.
 * Used by other Dot platforms to self-register with the intelligence layer.
 */
class PlatformController extends BaseApiController
{
    public function __construct(
        private readonly IntelligenceEngineService $engineService,
        private readonly ConnectPlatformAction $connectAction,
    ) {}

    /**
     * GET /api/v1/platforms
     * Returns the full platform catalog with connection status for the team.
     */
    public function catalog(): JsonResponse
    {
        $this->currentTeam(); // never fall through to an unscoped query

        $sources = DataSource::all()->keyBy('platform');

        $catalog = collect(IntelligenceEngineService::PLATFORMS)
            ->map(fn ($def, $key) => array_merge($def, [
                'key' => $key,
                'source' => $sources->get($key),
                'status' => $sources->get($key)?->status ?? 'not_connected',
            ]));

        return $this->success($catalog);
    }

    /**
     * GET /api/v1/platforms/connected
     * Returns only connected platforms.
     */
    public function connected(): JsonResponse
    {
        $this->currentTeam();

        // Sources named for platforms that are not in the roster (the retired
        // Dot.Fleet / Dot.CRM names) can never ingest, so they are not listed.
        $sources = DataSource::where('status', 'connected')
            ->whereIn('platform', array_keys(IntelligenceEngineService::PLATFORMS))
            ->get();

        return $this->success($sources);
    }

    /**
     * POST /api/v1/platforms/{platform}/connect
     * Connect a Dot platform to the intelligence layer.
     *
     * Body: { "base_url": "https://fleet.infodot.app" }
     */
    public function connect(Request $request, string $platform): JsonResponse
    {
        Gate::authorize('create', DataSource::class);

        $validated = $request->validate([
            'base_url' => 'nullable|url|max:255',
        ]);

        try {
            [$dataSource, $secret] = $this->connectAction->handleWithSecret(
                $this->currentTeam(),
                $platform,
                $validated['base_url'] ?? null,
            );

            // The webhook secret is write-only: it appears in this one response
            // (when newly issued) and nowhere else.
            $data = $secret === null
                ? $dataSource
                : array_merge($dataSource->toArray(), ['webhook_secret' => $secret]);

            return $this->success($data, "{$dataSource->display_name} connected.", 201);
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors());
        }
    }

    /**
     * DELETE /api/v1/platforms/{platform}
     * Disconnect a platform.
     */
    public function disconnect(string $platform): JsonResponse
    {
        $this->currentTeam();

        $source = DataSource::where('platform', $platform)->first();

        if (! $source) {
            return $this->error("Platform '{$platform}' is not connected.", 404);
        }

        Gate::authorize('delete', $source);

        $name = $source->display_name;
        $source->delete();

        return $this->success(null, "{$name} disconnected.");
    }

    /**
     * POST /api/v1/platforms/{platform}/webhook-secret
     * Issue a new ingest webhook secret (invalidating the previous one).
     * The plaintext is returned once and cannot be read back.
     */
    public function rotateWebhookSecret(string $platform): JsonResponse
    {
        $this->currentTeam();

        $source = DataSource::where('platform', $platform)->first();

        if (! $source) {
            return $this->error("Platform '{$platform}' is not connected.", 404);
        }

        Gate::authorize('update', $source);

        return $this->success(
            ['platform' => $platform, 'webhook_secret' => $source->rotateWebhookSecret()],
            'Webhook secret rotated. Store it now; it cannot be shown again.',
        );
    }

    /**
     * GET /api/v1/platforms/{platform}
     * Get details of a specific connected platform.
     */
    public function show(string $platform): JsonResponse
    {
        $this->currentTeam();

        $source = DataSource::where('platform', $platform)->first();

        if (! $source) {
            return $this->error("Platform '{$platform}' not found.", 404);
        }

        $definition = $this->engineService->getPlatform($platform);

        return $this->success([
            'source' => $source,
            'definition' => $definition,
        ]);
    }
}
