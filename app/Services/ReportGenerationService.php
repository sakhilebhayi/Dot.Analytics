<?php

namespace App\Services;

use App\Models\AnalyticsAlert;
use App\Models\ComputedMetric;
use App\Models\CrossPlatformInsight;
use App\Models\Recommendation;
use App\Models\Team;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Report Generation Service
 *
 * Generates analytics reports in multiple formats:
 *  - CSV  — streamed directly to the HTTP response
 *  - JSON — structured data for API consumers
 *  - HTML — print-optimised report suitable for PDF via browser print
 *
 * Each report includes metadata, generated timestamp, and a
 * confidence score where AI-generated data is present.
 */
class ReportGenerationService
{
    /**
     * Stream a CSV report directly to the browser.
     */
    public function streamCsv(Team $team, string $reportType, array $options = []): StreamedResponse
    {
        $filename = "{$reportType}_{$team->id}_".now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($team, $reportType, $options) {
            $output = fopen('php://output', 'w');

            [$headers, $rows] = $this->getData($team, $reportType, $options);

            fputcsv($output, $headers);
            foreach ($rows as $row) {
                fputcsv($output, $row);
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Generate a JSON report payload.
     */
    public function generateJson(Team $team, string $reportType, array $options = []): array
    {
        [$headers, $rows] = $this->getData($team, $reportType, $options);

        return [
            'report_type' => $reportType,
            'team' => $team->name,
            'generated_at' => now()->toIso8601String(),
            'columns' => $headers,
            'rows' => $rows,
            'row_count' => count($rows),
        ];
    }

    /**
     * Generate an HTML report for print/PDF.
     */
    public function generateHtml(Team $team, string $reportType, array $options = []): string
    {
        [$headers, $rows] = $this->getData($team, $reportType, $options);
        $title = ucwords(str_replace('_', ' ', $reportType));
        $date = now()->format('D, d M Y H:i');

        $headerHtml = implode('', array_map(fn ($h) => "<th>{$h}</th>", $headers));
        $rowsHtml = implode('', array_map(function ($row) {
            $cells = implode('', array_map(fn ($v) => '<td>'.e($v).'</td>', $row));

            return "<tr>{$cells}</tr>";
        }, $rows));

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Dot.Analytics — {$title}</title>
<style>
  body { font-family: -apple-system, Arial, sans-serif; font-size: 12px; color: #1f2937; }
  h1 { font-size: 18px; font-weight: 700; margin-bottom: 4px; }
  .meta { color: #6b7280; font-size: 11px; margin-bottom: 20px; }
  table { width: 100%; border-collapse: collapse; }
  th { background: #f3f4f6; text-align: left; padding: 8px; font-weight: 600; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; }
  td { padding: 7px 8px; border-bottom: 1px solid #f3f4f6; }
  tr:hover td { background: #f9fafb; }
  @media print { body { -webkit-print-color-adjust: exact; } }
</style>
</head>
<body>
<h1>Dot.Analytics — {$title}</h1>
<p class="meta">{$team->name} &middot; Generated {$date}</p>
<table>
  <thead><tr>{$headerHtml}</tr></thead>
  <tbody>{$rowsHtml}</tbody>
</table>
</body>
</html>
HTML;
    }

    // ─── Data providers ────────────────────────────────────────────────────────

    private function getData(Team $team, string $reportType, array $options): array
    {
        return match ($reportType) {
            'insights' => $this->insightsData($team, $options),
            'alerts' => $this->alertsData($team, $options),
            'recommendations' => $this->recommendationsData($team, $options),
            'metrics' => $this->metricsData($team, $options),
            default => [['Error'], [['Unknown report type: '.$reportType]]],
        };
    }

    private function insightsData(Team $team, array $options): array
    {
        $headers = ['Title', 'Type', 'Severity', 'Confidence', 'Platforms', 'Status', 'Discovered At'];
        $rows = CrossPlatformInsight::where('team_id', $team->id)
            ->when($options['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_at')
            ->limit($options['limit'] ?? 500)
            ->get()
            ->map(fn ($i) => [
                $i->title,
                $i->insight_type,
                $i->severity,
                round($i->confidence * 100).'%',
                implode(', ', $i->platforms_involved),
                $i->status,
                $i->created_at->format('Y-m-d H:i'),
            ])->toArray();

        return [$headers, $rows];
    }

    private function alertsData(Team $team, array $options): array
    {
        $headers = ['Title', 'Severity', 'Status', 'Description', 'Triggered At', 'Resolved At'];
        $rows = AnalyticsAlert::where('team_id', $team->id)
            ->orderByDesc('triggered_at')
            ->limit($options['limit'] ?? 500)
            ->get()
            ->map(fn ($a) => [
                $a->title,
                $a->severity,
                $a->status,
                $a->description,
                $a->triggered_at->format('Y-m-d H:i'),
                $a->resolved_at?->format('Y-m-d H:i') ?? '',
            ])->toArray();

        return [$headers, $rows];
    }

    private function recommendationsData(Team $team, array $options): array
    {
        $headers = ['Title', 'Engine', 'Priority', 'Status', 'Rationale', 'Created At'];
        $rows = Recommendation::where('team_id', $team->id)
            ->orderByDesc('created_at')
            ->limit($options['limit'] ?? 500)
            ->get()
            ->map(fn ($r) => [
                $r->title,
                $r->engine,
                $r->priority,
                $r->status,
                $r->rationale,
                $r->created_at->format('Y-m-d H:i'),
            ])->toArray();

        return [$headers, $rows];
    }

    private function metricsData(Team $team, array $options): array
    {
        $headers = ['Metric', 'Engine', 'Platform', 'Value', 'Unit', 'Period', 'Date'];
        $rows = ComputedMetric::where('team_id', $team->id)
            ->with('metricDefinition')
            ->when($options['period'] ?? null, fn ($q, $p) => $q->where('period', $p))
            ->orderByDesc('period_date')
            ->limit($options['limit'] ?? 500)
            ->get()
            ->map(fn ($m) => [
                $m->metricDefinition?->label,
                $m->metricDefinition?->engine,
                $m->metricDefinition?->source_platform,
                $m->value,
                $m->metricDefinition?->unit,
                $m->period,
                $m->period_date,
            ])->toArray();

        return [$headers, $rows];
    }
}
