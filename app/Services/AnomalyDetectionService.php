<?php

namespace App\Services;

use App\Models\AnalyticsAlert;
use App\Models\ComputedMetric;
use App\Models\Team;
use Illuminate\Support\Carbon;

/**
 * Anomaly Detection Service
 *
 * Continuously monitors computed metrics for statistically significant
 * deviations from historical baselines. Uses two complementary methods:
 *
 *  1. Z-Score — detects values that deviate more than N standard deviations
 *     from the rolling mean. Best for normally-distributed metrics.
 *
 *  2. IQR (Interquartile Range) — detects outliers using the Tukey fence.
 *     More robust for skewed distributions like revenue spikes.
 *
 * When an anomaly is detected, an AnalyticsAlert is automatically created
 * and surfaced in the alerts panel.
 */
class AnomalyDetectionService
{
    /** Number of standard deviations to flag as anomalous */
    private const Z_SCORE_THRESHOLD = 2.5;

    /** IQR multiplier for Tukey fence (1.5 = mild, 3.0 = extreme) */
    private const IQR_MULTIPLIER = 1.5;

    /** Minimum number of historical data points required */
    private const MIN_HISTORY = 7;

    /** Rolling window size for baseline computation */
    private const WINDOW_DAYS = 30;

    /**
     * Run anomaly detection across all active metrics for a team.
     * Returns the number of anomalies detected.
     */
    public function detectForTeam(Team $team): int
    {
        $since = Carbon::now()->subDays(self::WINDOW_DAYS);
        $detected = 0;

        // Group recent computed metrics by their definition
        $metricGroups = ComputedMetric::where('team_id', $team->id)
            ->where('period_date', '>=', $since)
            ->with('metricDefinition')
            ->orderBy('period_date')
            ->get()
            ->groupBy('metric_definition_id');

        foreach ($metricGroups as $definitionId => $metrics) {
            if ($metrics->count() < self::MIN_HISTORY) {
                continue; // Not enough history for reliable detection
            }

            $values = $metrics->pluck('value')->map(fn ($v) => (float) $v)->toArray();
            $latest = (float) $metrics->last()->value;
            $history = array_slice($values, 0, -1); // All except the latest point

            $anomaly = $this->detectAnomaly($latest, $history);

            if ($anomaly) {
                $def = $metrics->last()->metricDefinition;
                if ($def) {
                    $this->createAlert($team, $def->label, $def->key, $anomaly, $latest, $history);
                    $detected++;
                }
            }
        }

        return $detected;
    }

    /**
     * Detect whether the latest value is anomalous compared to history.
     *
     * @param  float  $value  The latest observed value
     * @param  float[]  $history  Historical values (without the latest)
     * @return array|null Anomaly details or null if no anomaly
     */
    public function detectAnomaly(float $value, array $history): ?array
    {
        if (count($history) < self::MIN_HISTORY) {
            return null;
        }

        $zScore = $this->zScoreAnomaly($value, $history);
        if ($zScore !== null) {
            return $zScore;
        }

        return $this->iqrAnomaly($value, $history);
    }

    /**
     * Z-Score anomaly detection.
     */
    public function zScoreAnomaly(float $value, array $history): ?array
    {
        $mean = $this->mean($history);
        $stdDev = $this->stdDev($history, $mean);

        if ($stdDev < 0.001) {
            return null; // No variance — skip
        }

        $zScore = abs(($value - $mean) / $stdDev);
        $isAbove = $value > $mean;

        if ($zScore >= self::Z_SCORE_THRESHOLD) {
            return [
                'method' => 'z_score',
                'z_score' => round($zScore, 2),
                'direction' => $isAbove ? 'spike' : 'drop',
                'mean' => round($mean, 4),
                'std_dev' => round($stdDev, 4),
                'deviation_pct' => round(abs($value - $mean) / max(abs($mean), 0.001) * 100, 1),
                'severity' => $zScore >= 4 ? 'critical' : 'warning',
            ];
        }

        return null;
    }

    /**
     * IQR (Tukey fence) anomaly detection.
     */
    public function iqrAnomaly(float $value, array $history): ?array
    {
        sort($history);
        $n = count($history);
        $q1 = $history[(int) floor($n * 0.25)];
        $q3 = $history[(int) floor($n * 0.75)];
        $iqr = $q3 - $q1;

        if ($iqr < 0.001) {
            return null;
        }

        $lowerFence = $q1 - self::IQR_MULTIPLIER * $iqr;
        $upperFence = $q3 + self::IQR_MULTIPLIER * $iqr;

        if ($value < $lowerFence || $value > $upperFence) {
            $isAbove = $value > $upperFence;

            return [
                'method' => 'iqr',
                'direction' => $isAbove ? 'spike' : 'drop',
                'q1' => round($q1, 4),
                'q3' => round($q3, 4),
                'iqr' => round($iqr, 4),
                'fence' => $isAbove ? round($upperFence, 4) : round($lowerFence, 4),
                'severity' => 'warning',
            ];
        }

        return null;
    }

    /**
     * Compute a simple forecast for the next N periods using linear regression.
     *
     * @param  float[]  $values  Ordered historical values (oldest first)
     * @param  int  $steps  Number of future steps to forecast
     * @return float[]
     */
    public function forecast(array $values, int $steps = 7): array
    {
        $n = count($values);
        if ($n < 2) {
            return array_fill(0, $steps, end($values) ?: 0.0);
        }

        // Ordinary Least Squares linear regression
        $xMean = ($n - 1) / 2;
        $yMean = $this->mean($values);

        $numerator = 0.0;
        $denominator = 0.0;

        foreach ($values as $i => $y) {
            $x = $i - $xMean;
            $numerator += $x * ($y - $yMean);
            $denominator += $x * $x;
        }

        $slope = $denominator > 0 ? $numerator / $denominator : 0;
        $intercept = $yMean - $slope * $xMean;

        $forecast = [];
        for ($i = 0; $i < $steps; $i++) {
            $x = $n + $i;   // uncentered index — intercept is at x=0
            $forecast[] = round($intercept + $slope * $x, 4);
        }

        return $forecast;
    }

    // ─── Statistical helpers ──────────────────────────────────────────────────

    public function mean(array $values): float
    {
        return count($values) > 0 ? array_sum($values) / count($values) : 0.0;
    }

    public function stdDev(array $values, ?float $mean = null): float
    {
        $mean ??= $this->mean($values);
        $n = count($values);

        if ($n < 2) {
            return 0.0;
        }

        $variance = array_sum(array_map(fn ($v) => ($v - $mean) ** 2, $values)) / ($n - 1);

        return sqrt($variance);
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function createAlert(Team $team, string $label, string $metricKey, array $anomaly, float $value, array $history): void
    {
        $direction = $anomaly['direction'] === 'spike' ? 'spiked above' : 'dropped below';
        $method = strtoupper($anomaly['method']);
        $pct = $anomaly['deviation_pct'] ?? null;

        AnalyticsAlert::create([
            'team_id' => $team->id,
            'title' => "Anomaly detected: {$label}",
            'description' => $pct
                ? "{$label} {$direction} normal range by {$pct}% ({$method} detection)."
                : "{$label} {$direction} normal range ({$method} detection).",
            'severity' => $anomaly['severity'],
            'status' => 'open',
            'context' => array_merge($anomaly, [
                'metric_key' => $metricKey,
                'detected_value' => $value,
                'history_mean' => round($this->mean($history), 4),
                'source' => 'anomaly_detection',
            ]),
            'triggered_at' => Carbon::now(),
        ]);
    }
}
