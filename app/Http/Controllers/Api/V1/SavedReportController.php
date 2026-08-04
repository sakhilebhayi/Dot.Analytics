<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AnalyticsReport;
use App\Models\ReportRun;
use App\Services\ReportGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Saved Reports Controller — v1
 *
 * Manage persistent, schedulable report definitions.
 * Each report definition has a type and config; runs are tracked
 * in report_runs for history and replay.
 */
class SavedReportController extends BaseApiController
{
    private const VALID_TYPES = ['insights', 'alerts', 'recommendations', 'metrics'];

    public function __construct(
        private readonly ReportGenerationService $reportService,
    ) {}

    /**
     * GET /api/v1/saved-reports
     * List all saved reports for the team.
     */
    public function index(): JsonResponse
    {
        $reports = AnalyticsReport::with('latestRun')
            ->orderByDesc('created_at')
            ->get();

        return $this->success($reports);
    }

    /**
     * POST /api/v1/saved-reports
     * Create a new saved report definition.
     *
     * Body: { title, type, description?, cron_expression?, config? }
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'           => 'required|string|max:120',
            'type'            => 'required|string|in:' . implode(',', self::VALID_TYPES),
            'description'     => 'nullable|string|max:500',
            'cron_expression' => 'nullable|string|max:100',
            'config'          => 'nullable|array',
        ]);

        $report = AnalyticsReport::create(array_merge($validated, [
            'team_id'     => Auth::user()->currentTeam->id,
            'user_id'     => Auth::id(),
            'report_type' => ! empty($validated['cron_expression']) ? 'scheduled' : 'ad_hoc',
        ]));

        return $this->success($report, 'Report definition created.', 201);
    }

    /**
     * POST /api/v1/saved-reports/{id}/run
     * Execute a saved report and persist the output.
     */
    public function run(int $id): JsonResponse
    {
        $team   = Auth::user()->currentTeam;
        $report = AnalyticsReport::findOrFail($id);

        $run = ReportRun::create([
            'analytics_report_id' => $report->id,
            'status'              => 'running',
            'started_at'          => now(),
        ]);

        try {
            $output = $this->reportService->generateJson(
                $team,
                $report->type ?? 'insights',
                $report->config ?? [],
            );

            $run->update([
                'status'       => 'completed',
                'output'       => $output,
                'completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $run->update(['status' => 'failed', 'completed_at' => now()]);
            return $this->error('Report run failed: ' . $e->getMessage(), 500);
        }

        return $this->success(['run' => $run, 'output' => $output]);
    }

    /**
     * GET /api/v1/saved-reports/{id}/runs
     * Get run history for a saved report.
     */
    public function runs(int $id): JsonResponse
    {
        $report = AnalyticsReport::findOrFail($id);

        $runs = ReportRun::where('analytics_report_id', $report->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return $this->success($runs);
    }

    /**
     * DELETE /api/v1/saved-reports/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $report = AnalyticsReport::findOrFail($id);
        $report->delete();

        return $this->success(null, 'Report deleted.');
    }
}
