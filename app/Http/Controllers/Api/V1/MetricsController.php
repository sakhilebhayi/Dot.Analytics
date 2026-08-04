<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AiModelUsage;
use App\Models\ComputedMetric;
use App\Models\MetricDefinition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Metrics API — v1
 *
 * Expose computed metrics and metric definitions over REST.
 * Allows external systems and embedded dashboards to consume metrics.
 */
class MetricsController extends BaseApiController
{
    /**
     * GET /api/v1/metrics/definitions
     * Returns all metric definitions, optionally filtered by engine or platform.
     */
    public function definitions(Request $request): JsonResponse
    {
        $definitions = MetricDefinition::query()
            ->when($request->input('engine'),   fn ($q) => $q->where('engine', $request->input('engine')))
            ->when($request->input('platform'), fn ($q) => $q->where('source_platform', $request->input('platform')))
            ->orderBy('engine')
            ->orderBy('label')
            ->get();

        return $this->success($definitions);
    }

    /**
     * GET /api/v1/metrics
     * Returns computed metric values for the team.
     *
     * Query params: period, period_date, metric_key, engine
     */
    public function index(Request $request): JsonResponse
    {
        $metrics = ComputedMetric::with('metricDefinition')
            ->when($request->input('period'),      fn ($q) => $q->where('period', $request->input('period')))
            ->when($request->input('period_date'), fn ($q) => $q->where('period_date', $request->input('period_date')))
            ->when($request->input('metric_key'),  fn ($q) => $q->whereHas('metricDefinition', fn ($mq) =>
                $mq->where('key', $request->input('metric_key'))
            ))
            ->orderByDesc('period_date')
            ->limit(200)
            ->get()
            ->map(fn ($m) => [
                'key'    => $m->metricDefinition?->key,
                'label'  => $m->metricDefinition?->label,
                'engine' => $m->metricDefinition?->engine,
                'unit'   => $m->metricDefinition?->unit,
                'value'  => $m->value,
                'period' => $m->period,
                'date'   => $m->period_date,
            ]);

        return $this->success($metrics);
    }

    /**
     * GET /api/v1/metrics/ai-usage
     * Returns AI model usage and cost summary for the team.
     */
    public function aiUsage(Request $request): JsonResponse
    {
        $team = Auth::user()->currentTeam;

        $byProvider = AiModelUsage::selectRaw('provider, model, count(*) as calls, sum(input_tokens) as total_input, sum(output_tokens) as total_output, sum(cost_usd) as total_cost_usd, avg(latency_ms) as avg_latency_ms')
            ->groupBy('provider', 'model')
            ->orderByDesc('total_cost_usd')
            ->get();

        return $this->success([
            'by_model'    => $byProvider,
            'total_cost'  => AiModelUsage::totalCostForTeam($team->id),
            'total_calls' => AiModelUsage::count(),
        ]);
    }
}
