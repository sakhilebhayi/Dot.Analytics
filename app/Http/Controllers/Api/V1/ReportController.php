<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\ReportGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reports API — v1
 *
 * Generate and export analytics reports in CSV, JSON, and HTML formats.
 */
class ReportController extends BaseApiController
{
    private const REPORT_TYPES = ['insights', 'alerts', 'recommendations', 'metrics'];

    public function __construct(
        private readonly ReportGenerationService $reportService,
    ) {}

    /**
     * GET /api/v1/reports/{type}
     * Returns a JSON report.
     *
     * Query params: status, period, limit
     */
    public function json(Request $request, string $type): JsonResponse
    {
        if (! in_array($type, self::REPORT_TYPES)) {
            return $this->error("Unknown report type '{$type}'. Valid: " . implode(', ', self::REPORT_TYPES), 422);
        }

        $team   = $this->currentTeam();
        $report = $this->reportService->generateJson($team, $type, $request->only('status', 'period', 'limit'));

        return $this->success($report);
    }

    /**
     * GET /api/v1/reports/{type}/csv
     * Streams a CSV download.
     */
    public function csv(Request $request, string $type): StreamedResponse|JsonResponse
    {
        if (! in_array($type, self::REPORT_TYPES)) {
            return $this->error("Unknown report type '{$type}'.", 422);
        }

        $team = $this->currentTeam();
        return $this->reportService->streamCsv($team, $type, $request->only('status', 'period', 'limit'));
    }

    /**
     * GET /api/v1/reports/{type}/html
     * Returns a print-ready HTML report (suitable for browser print to PDF).
     */
    public function html(Request $request, string $type): \Illuminate\Http\Response|JsonResponse
    {
        if (! in_array($type, self::REPORT_TYPES)) {
            return $this->error("Unknown report type '{$type}'.", 422);
        }

        $team = $this->currentTeam();
        $html = $this->reportService->generateHtml($team, $type, $request->only('status', 'period', 'limit'));

        return response($html, 200, ['Content-Type' => 'text/html']);
    }
}
