<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Analytics\RunIntelligenceEnginesAction;
use App\Models\CrossPlatformInsight;
use App\Services\IntelligenceEngineService;
use App\Services\KnowledgeGraphService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Intelligence API — v1
 *
 * Exposes cross-platform intelligence capabilities over REST.
 * All responses include confidence scores and evidence chains
 * for explainability and audit compliance.
 */
class IntelligenceController extends BaseApiController
{
    public function __construct(
        private readonly IntelligenceEngineService   $engineService,
        private readonly KnowledgeGraphService       $graphService,
        private readonly RunIntelligenceEnginesAction $runEngines,
    ) {}

    /**
     * GET /api/v1/intelligence/engines
     * Returns all intelligence engines with their active status for the team.
     */
    public function engines(): JsonResponse
    {
        $team      = Auth::user()->currentTeam;
        $connected = $team->dataSources()->where('status', 'connected')->pluck('platform')->toArray();
        $active    = $this->engineService->getActiveEngines($connected);

        return $this->success([
            'engines'          => IntelligenceEngineService::ENGINES,
            'active'           => $active,
            'active_count'     => count($active),
            'connected_platforms' => $connected,
        ]);
    }

    /**
     * POST /api/v1/intelligence/run
     * Dispatch intelligence engine jobs for the team.
     *
     * Body: { "engines": ["operational", "financial"] }  (optional, runs all if omitted)
     */
    public function run(Request $request): JsonResponse
    {
        $team     = Auth::user()->currentTeam;
        $engines  = $request->input('engines');

        $dispatched = $this->runEngines->handle($team, $engines);

        return $this->success(
            ['engines_dispatched' => $dispatched],
            "Dispatched {$dispatched} intelligence engine(s).",
        );
    }

    /**
     * GET /api/v1/intelligence/insights
     * Returns cross-platform insights for the team.
     *
     * Query params: type, severity, status, per_page
     */
    public function insights(Request $request): JsonResponse
    {
        $team    = Auth::user()->currentTeam;
        $perPage = min((int) $request->input('per_page', 20), 100);

        $insights = CrossPlatformInsight::where('team_id', $team->id)
            ->when($request->input('type'),     fn ($q) => $q->where('insight_type', $request->input('type')))
            ->when($request->input('severity'), fn ($q) => $q->where('severity', $request->input('severity')))
            ->when($request->input('status'),   fn ($q) => $q->where('status', $request->input('status')))
            ->orderByRaw("CASE severity WHEN 'critical' THEN 1 WHEN 'warning' THEN 2 ELSE 3 END")
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return $this->paginated($insights);
    }

    /**
     * GET /api/v1/intelligence/graph
     * Returns knowledge graph statistics for the team.
     */
    public function graph(): JsonResponse
    {
        $team  = Auth::user()->currentTeam;
        $stats = $this->graphService->getStats($team);

        return $this->success($stats);
    }

    /**
     * POST /api/v1/intelligence/graph/traverse
     * Traverse the graph from an entity to find all related entities.
     *
     * Body: { "entity_type": "customer", "entity_id": "42", "max_depth": 3 }
     */
    public function traverse(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'entity_type' => 'required|string|max:50',
            'entity_id'   => 'required|string|max:100',
            'max_depth'   => 'integer|min:1|max:5',
        ]);

        $team  = Auth::user()->currentTeam;
        $nodes = $this->graphService->traverse(
            $team,
            $validated['entity_type'],
            $validated['entity_id'],
            $validated['max_depth'] ?? 3,
        );

        return $this->success([
            'nodes'      => $nodes->values(),
            'node_count' => $nodes->count(),
        ]);
    }
}
