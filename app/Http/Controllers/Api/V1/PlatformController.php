<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Analytics\ConnectPlatformAction;
use App\Models\DataSource;
use App\Services\IntelligenceEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        private readonly ConnectPlatformAction     $connectAction,
    ) {}

    /**
     * GET /api/v1/platforms
     * Returns the full platform catalog with connection status for the team.
     */
    public function catalog(): JsonResponse
    {
        $sources = DataSource::all()->keyBy('platform');

        $catalog = collect(IntelligenceEngineService::PLATFORMS)
            ->map(fn ($def, $key) => array_merge($def, [
                'key'    => $key,
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
        $sources = DataSource::where('status', 'connected')->get();

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
        $validated = $request->validate([
            'base_url' => 'nullable|url|max:255',
        ]);

        try {
            $dataSource = $this->connectAction->handle(
                $this->currentTeam(),
                $platform,
                $validated['base_url'] ?? null,
            );

            return $this->success($dataSource, "{$dataSource->display_name} connected.", 201);
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
        $source = DataSource::where('platform', $platform)->first();

        if (! $source) {
            return $this->error("Platform '{$platform}' is not connected.", 404);
        }

        $name = $source->display_name;
        $source->delete();

        return $this->success(null, "{$name} disconnected.");
    }

    /**
     * GET /api/v1/platforms/{platform}
     * Get details of a specific connected platform.
     */
    public function show(string $platform): JsonResponse
    {
        $source = DataSource::where('platform', $platform)->first();

        if (! $source) {
            return $this->error("Platform '{$platform}' not found.", 404);
        }

        $definition = $this->engineService->getPlatform($platform);

        return $this->success([
            'source'     => $source,
            'definition' => $definition,
        ]);
    }
}
