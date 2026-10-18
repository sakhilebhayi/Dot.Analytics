<?php

namespace App\Services\Metrics;

use App\Models\AnalyticsAlert;
use App\Models\ComputedMetric;
use App\Models\Team;
use App\Support\MetricFormat;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Flags a metric whose latest daily point is far from its own recent history,
 * using the median absolute deviation (MAD), which a single earlier outlier
 * cannot drag around the way a mean and standard deviation can.
 *
 *   deviation = |latest - median(prior)| / max(MAD(prior), 1% of |median|, kind floor)
 *   flag when deviation > metrics.anomaly.mad_threshold   (default 3.5)
 *
 * Rules, each of which keeps a quiet metric quiet:
 *  - at least metrics.anomaly.min_prior_points (7) prior daily points, taken from
 *    the metrics.anomaly.window_days (30) before the latest point. Missing days
 *    are not filled in;
 *  - the latest point must not be stale: an old value is not "now";
 *  - prior points in a different currency are not history for a money metric;
 *  - one alert per metric per day (the dedupe key), and an alert that was
 *    acknowledged or resolved is never reopened by a later run.
 *
 * It reads only computed metrics, so it can only ever flag what a platform sent.
 * No AI is involved. The alert carries its provenance: the metric, the window,
 * the value, the baseline and the threshold.
 */
class MetricAnomalyDetector
{
    public function __construct(private readonly MetricCatalogue $catalogue = new MetricCatalogue) {}

    /**
     * @return array{checked: int, flagged: int, created: int, skipped_insufficient: int, skipped_stale: int}
     */
    public function detectForTeam(Team $team, ?CarbonInterface $now = null): array
    {
        $now = $now ? Carbon::instance($now) : Carbon::now();
        $result = ['checked' => 0, 'flagged' => 0, 'created' => 0, 'skipped_insufficient' => 0, 'skipped_stale' => 0];

        $config = config('metrics.anomaly');
        $rows = ComputedMetric::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('period', 'daily')
            ->where('period_date', '>=', $now->copy()->subDays(ComputeMetrics::HISTORY_DAYS)->toDateString())
            ->with('metricDefinition')
            ->orderBy('period_date')
            ->get()
            ->groupBy('metric_definition_id');

        foreach ($rows as $series) {
            $latest = $series->last();
            $parsed = $latest->metricDefinition ? $this->catalogue->parse($latest->metricDefinition->key) : null;
            if ($parsed === null) {
                continue;
            }

            $result['checked']++;

            if ($latest->isStale($now)) {
                $result['skipped_stale']++;

                continue;
            }

            $prior = $this->priorPoints($series, $latest, (int) $config['window_days']);
            if ($prior->count() < (int) $config['min_prior_points']) {
                $result['skipped_insufficient']++;

                continue;
            }

            $type = $this->catalogue->metric($parsed['platform'], $parsed['metric'])['type'];
            $verdict = $this->evaluate($prior->map(fn (ComputedMetric $m) => (float) $m->value)->all(), (float) $latest->value, $type);

            if ($verdict['score'] <= (float) $config['mad_threshold']) {
                continue;
            }

            $result['flagged']++;
            if ($this->raise($team, $latest, $prior, $verdict, $parsed, $type, $now)) {
                $result['created']++;
            }
        }

        return $result;
    }

    /**
     * The statistics, kept free of models so they can be tested on plain numbers.
     *
     * @param  list<float>  $prior  at least one value
     * @return array{median: float, mad: float, scale: float, score: float, direction: string}
     */
    public function evaluate(array $prior, float $latest, string $type): array
    {
        $median = $this->median($prior);
        $mad = $this->median(array_map(fn (float $v) => abs($v - $median), $prior));
        $floor = (float) ($this->catalogue->type($type)['min_scale'] ?? 1.0);
        $scale = max($mad, (float) config('metrics.anomaly.min_scale_ratio', 0.01) * abs($median), $floor);

        return [
            'median' => $median,
            'mad' => $mad,
            'scale' => $scale,
            'score' => abs($latest - $median) / $scale,
            'direction' => $latest >= $median ? 'spike' : 'drop',
        ];
    }

    /**
     * @param  Collection<int, ComputedMetric>  $series  ascending by period_date
     * @return Collection<int, ComputedMetric>
     */
    private function priorPoints(Collection $series, ComputedMetric $latest, int $windowDays): Collection
    {
        $from = $latest->period_date->copy()->subDays($windowDays)->startOfDay();

        return $series
            ->filter(fn (ComputedMetric $m) => $m->period_date->lt($latest->period_date)
                && $m->period_date->gte($from)
                && $m->currency === $latest->currency)
            ->values();
    }

    /**
     * @param  Collection<int, ComputedMetric>  $prior
     * @param  array{median: float, mad: float, scale: float, score: float, direction: string}  $verdict
     * @param  array{platform: string, metric: string}  $parsed
     */
    private function raise(Team $team, ComputedMetric $latest, Collection $prior, array $verdict, array $parsed, string $type, Carbon $now): bool
    {
        $definition = $latest->metricDefinition;
        $threshold = (float) config('metrics.anomaly.mad_threshold');
        $date = $latest->period_date->toDateString();
        $currency = $latest->currency;
        $platformLabel = $this->catalogue->platform($parsed['platform'])['label'] ?? $parsed['platform'];

        $description = sprintf(
            '%s was %s on %s, %s its usual level: the median of the previous %d daily point(s) is %s (MAD %s). That is %s median absolute deviations away; the threshold is %s.',
            $definition->label,
            MetricFormat::value($latest->value, $type, $currency),
            $date,
            $verdict['direction'] === 'spike' ? 'above' : 'below',
            $prior->count(),
            MetricFormat::value($verdict['median'], $type, $currency),
            MetricFormat::value($verdict['mad'], $type, $currency),
            rtrim(rtrim(number_format($verdict['score'], 1), '0'), '.'),
            rtrim(rtrim(number_format($threshold, 1), '0'), '.'),
        );

        $values = $prior->map(fn (ComputedMetric $m) => [
            'date' => $m->period_date->toDateString(),
            'value' => (float) $m->value,
        ])->values()->all();

        try {
            $alert = AnalyticsAlert::withoutGlobalScopes()->firstOrCreate(
                ['team_id' => $team->id, 'dedupe_key' => "anomaly:{$definition->id}:{$date}"],
                [
                    'metric_definition_id' => $definition->id,
                    'title' => "Anomaly detected: {$definition->label} ({$platformLabel})",
                    'description' => $description,
                    'severity' => $verdict['score'] >= 2 * $threshold ? 'critical' : 'warning',
                    'status' => 'open',
                    'triggered_at' => $now,
                    'context' => [
                        'source' => 'metric_anomaly',
                        'metric_key' => $definition->key,
                        'platform' => $parsed['platform'],
                        'direction' => $verdict['direction'],
                        'value' => (float) $latest->value,
                        'currency' => $currency,
                        'period_date' => $date,
                        'observed_at' => $latest->observed_at?->toIso8601String(),
                        'baseline' => [
                            'median' => $verdict['median'],
                            'mad' => $verdict['mad'],
                            'scale' => $verdict['scale'],
                        ],
                        'score' => round($verdict['score'], 2),
                        'threshold' => $threshold,
                        'window' => [
                            'start' => $prior->first()->period_date->toDateString(),
                            'end' => $date,
                            'prior_points' => $prior->count(),
                            'values' => $values,
                        ],
                        'is_demo' => (bool) $latest->is_demo,
                    ],
                ],
            );
        } catch (UniqueConstraintViolationException) {
            return false; // another run raised it first
        }

        return $alert->wasRecentlyCreated;
    }

    /** @param  list<float>  $values */
    private function median(array $values): float
    {
        sort($values);
        $n = count($values);
        $mid = intdiv($n, 2);

        return $n % 2 === 1 ? $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2;
    }
}
