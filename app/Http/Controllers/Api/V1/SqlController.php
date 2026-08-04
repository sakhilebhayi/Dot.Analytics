<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\DataConnector;
use App\Services\AiSqlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AI SQL Controller — v1
 *
 * Accepts natural-language questions and returns SQL + results.
 * All generated SQL is logged for auditability.
 * Only read-only (SELECT) queries are executed.
 */
class SqlController extends BaseApiController
{
    public function __construct(
        private readonly AiSqlService $sqlService,
    ) {}

    /**
     * POST /api/v1/sql/query
     *
     * Body:
     *   question:       "What were the top 5 customers by revenue last month?"
     *   connector_id:   42  (ID of the DataConnector to query)
     *   schema:         {...}  (optional — cached schema to avoid re-fetching)
     */
    public function query(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'question'     => 'required|string|min:5|max:500',
            'connector_id' => 'required|integer|exists:data_connectors,id',
            'schema'       => 'nullable|array',
        ]);

        $team      = $this->currentTeam();
        $connector = DataConnector::findOrFail($validated['connector_id']);

        if (! $connector->isActive()) {
            return $this->error("Connector '{$connector->name}' is not active.", 422);
        }

        try {
            $result = $this->sqlService->query(
                question:      $validated['question'],
                connector:     $connector,
                teamId:        $team->id,
                schemaSummary: $validated['schema'] ?? null,
            );

            return $this->success($result);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            return $this->error('Query execution failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/v1/sql/generate
     *
     * Generate SQL from a question without executing it.
     * Useful for previewing before running.
     */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'question'     => 'required|string|min:5|max:500',
            'connector_id' => 'required|integer|exists:data_connectors,id',
            'schema'       => 'nullable|array',
        ]);

        $team      = $this->currentTeam();
        $connector = DataConnector::findOrFail($validated['connector_id']);

        $schema = $validated['schema'] ?? [];
        $sql    = $this->sqlService->generateSql($validated['question'], $schema, $team->id);

        return $this->success(['sql' => $sql, 'question' => $validated['question']]);
    }
}
