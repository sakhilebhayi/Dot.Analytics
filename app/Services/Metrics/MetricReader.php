<?php

namespace App\Services\Metrics;

use App\Models\AnalyticsAlert;
use App\Models\ComputedMetric;
use App\Models\DataSource;
use App\Models\MetricDefinition;
use App\Models\Team;
use App\Support\MetricFormat;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Everything the dashboard shows about metrics, read from computed_metrics and
 * nothing else: it has no demo numbers, no fallbacks and no defaults.
 *
 * A tile has exactly one of three states:
 *   ok       a value reported within the stale window
 *   stale    a value was reported once but not recently; the last value is shown, marked
 *   no_data  the platform is connected but never reported this metric (value null, never 0)
 *
 * A platform that is not connected has no tiles at all.
 */
class MetricReader
{
    /** Points kept on a tile for its sparkline. */
    public const SPARK_DAYS = 30;

    public function __construct(private readonly MetricCatalogue $catalogue = new MetricCatalogue) {}

    /**
     * @return array{platforms: list<array<string, mixed>>, summary: array<string, mixed>}
     */
    public function dashboard(Team $team, ?CarbonInterface $now = null): array
    {
        $now = $now ? Carbon::instance($now) : Carbon::now();

        $sources = DataSource::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('status', 'connected')
            ->whereIn('platform', $this->catalogue->platformKeys())
            ->get()
            ->keyBy('platform');

        $rows = $this->rowsFor($team, $now);

        $platforms = [];
        $metricsReporting = 0;
        $stale = 0;
        $reportingPlatforms = 0;
        $hasDemo = false;

        foreach ($this->catalogue->platforms() as $platform => $definition) {
            $source = $sources->get($platform);
            $entry = [
                'key' => $platform,
                'label' => $definition['label'],
                'connected' => $source !== null,
                'last_synced_at' => $source?->last_synced_at?->toIso8601String(),
                'has_data' => false,
                'tiles' => [],
            ];

            if ($source !== null) {
                foreach (array_keys($definition['metrics']) as $metric) {
                    $tile = $this->buildTile($platform, $metric, $rows->get($this->catalogue->fullKey($platform, $metric)), $now);
                    $entry['tiles'][] = $tile;

                    if ($tile['state'] !== 'no_data') {
                        $entry['has_data'] = true;
                        $metricsReporting++;
                        $stale += $tile['state'] === 'stale' ? 1 : 0;
                        $hasDemo = $hasDemo || $tile['is_demo'];
                    }
                }
                $reportingPlatforms += $entry['has_data'] ? 1 : 0;
            }

            $platforms[] = $entry;
        }

        $anomalyKeys = $this->openAnomalyKeys($team);
        $open = count($anomalyKeys);

        return [
            'platforms' => $platforms,
            'summary' => [
                'connected' => $sources->count(),
                'reporting_platforms' => $reportingPlatforms,
                'metrics_reporting' => $metricsReporting,
                'stale_metrics' => $stale,
                'open_anomalies' => $open,
                'anomaly_keys' => $anomalyKeys,
                'has_demo' => $hasDemo,
                'headline' => $this->headline($sources->count(), $reportingPlatforms, $open, $stale),
            ],
        ];
    }

    /**
     * What the dashboard shows for a user who has no team yet: every platform, none connected.
     *
     * @return array{platforms: list<array<string, mixed>>, summary: array<string, mixed>}
     */
    public function empty(): array
    {
        $platforms = [];
        foreach ($this->catalogue->platforms() as $platform => $definition) {
            $platforms[] = ['key' => $platform, 'label' => $definition['label'], 'connected' => false, 'last_synced_at' => null, 'has_data' => false, 'tiles' => []];
        }

        return ['platforms' => $platforms, 'summary' => [
            'connected' => 0, 'reporting_platforms' => 0, 'metrics_reporting' => 0, 'stale_metrics' => 0,
            'open_anomalies' => 0, 'anomaly_keys' => [], 'has_demo' => false, 'headline' => $this->headline(0, 0, 0, 0),
        ]];
    }

    /**
     * One metric's tile, or null if the key is not in the catalogue or its
     * platform is not connected for the team.
     *
     * @return array<string, mixed>|null
     */
    public function tile(Team $team, string $fullKey, ?CarbonInterface $now = null): ?array
    {
        $parsed = $this->catalogue->parse($fullKey);
        if ($parsed === null || ! $this->isConnected($team, $parsed['platform'])) {
            return null;
        }

        $now = $now ? Carbon::instance($now) : Carbon::now();

        return $this->buildTile($parsed['platform'], $parsed['metric'], $this->rowsFor($team, $now, [$fullKey])->get($fullKey), $now);
    }

    /**
     * The stored daily points of one metric for a trend chart, ascending, and the
     * days on which an anomaly alert was raised for it (date => alert id).
     *
     * @return array{key: string, metric: string, platform: string, label: string, type: string, currency: string|null, points: list<array{date: string, value: float, is_demo: bool}>, anomalies: array<string, int>}|null
     */
    public function series(Team $team, string $fullKey, int $days = 30, ?CarbonInterface $now = null): ?array
    {
        $parsed = $this->catalogue->parse($fullKey);
        if ($parsed === null || ! $this->isConnected($team, $parsed['platform'])) {
            return null;
        }

        $now = $now ? Carbon::instance($now) : Carbon::now();
        $spec = $this->catalogue->metric($parsed['platform'], $parsed['metric']);
        $definition = MetricDefinition::where('key', $fullKey)->first();

        $rows = $definition
            ? ComputedMetric::withoutGlobalScopes()
                ->where('team_id', $team->id)
                ->where('metric_definition_id', $definition->id)
                ->where('period', 'daily')
                ->where('period_date', '>=', $now->copy()->subDays($days)->toDateString())
                ->orderBy('period_date')
                ->get()
            : collect();

        $anomalies = $definition
            ? AnalyticsAlert::withoutGlobalScopes()
                ->where('team_id', $team->id)
                ->where('metric_definition_id', $definition->id)
                ->where('dedupe_key', 'like', 'anomaly:%')
                ->get()
                ->mapWithKeys(fn (AnalyticsAlert $a) => [($a->context['period_date'] ?? '') => $a->id])
                ->forget('')
                ->all()
            : [];

        $points = $rows->map(fn (ComputedMetric $m) => [
            'date' => $m->period_date->toDateString(),
            'value' => (float) $m->value,
            'is_demo' => $m->is_demo,
        ])->values()->all();

        return [
            'key' => $fullKey,
            'metric' => $parsed['metric'],
            'platform' => $parsed['platform'],
            'label' => $spec['label'],
            'type' => $spec['type'],
            'currency' => $rows->last()?->currency,
            'points' => $points,
            'anomalies' => array_intersect_key($anomalies, array_flip(array_column($points, 'date'))),
        ];
    }

    /** The catalogue metrics of every connected platform, for a metric picker. @return array<string, array<string, string>> platform label => [full key => label] */
    public function pickable(Team $team): array
    {
        $connected = DataSource::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('status', 'connected')
            ->whereIn('platform', $this->catalogue->platformKeys())
            ->pluck('platform');

        $out = [];
        foreach ($connected as $platform) {
            $definition = $this->catalogue->platform($platform);
            foreach ($definition['metrics'] as $key => $spec) {
                $out[$definition['label']][$this->catalogue->fullKey($platform, $key)] = $spec['label'];
            }
        }

        return $out;
    }

    private function isConnected(Team $team, string $platform): bool
    {
        return DataSource::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('platform', $platform)
            ->where('status', 'connected')
            ->exists();
    }

    /**
     * The recent computed rows per metric key (ascending). Rows older than
     * HISTORY_DAYS are not read: nothing that old is on a tile.
     *
     * @param  list<string>|null  $only  full keys
     * @return Collection<string, Collection<int, ComputedMetric>>|Collection<array-key, mixed>
     */
    private function rowsFor(Team $team, Carbon $now, ?array $only = null): Collection
    {
        $definitions = MetricDefinition::query()
            ->when($only, fn ($q) => $q->whereIn('key', $only))
            ->whereIn('source_platform', $this->catalogue->platformKeys())
            ->get()
            ->keyBy('id');

        if ($definitions->isEmpty()) {
            return collect();
        }

        return ComputedMetric::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('period', 'daily')
            ->whereIn('metric_definition_id', $definitions->keys())
            ->where('period_date', '>=', $now->copy()->subDays(ComputeMetrics::HISTORY_DAYS)->toDateString())
            ->orderBy('period_date')
            ->get()
            ->groupBy(fn (ComputedMetric $m) => $definitions[$m->metric_definition_id]->key);
    }

    /**
     * @param  Collection<int, ComputedMetric>|null  $rows  ascending
     * @return array<string, mixed>
     */
    private function buildTile(string $platform, string $metric, ?Collection $rows, Carbon $now): array
    {
        $spec = $this->catalogue->metric($platform, $metric);
        $platformLabel = $this->catalogue->platform($platform)['label'];
        $base = [
            'key' => $this->catalogue->fullKey($platform, $metric),
            'platform' => $platform,
            'platform_label' => $platformLabel,
            'metric' => $metric,
            'label' => $spec['label'],
            'type' => $spec['type'],
        ];

        $latest = $rows?->last();
        if ($latest === null) {
            return $base + [
                'state' => 'no_data', 'currency' => null, 'value' => null, 'raw' => null, 'previous' => null,
                'delta' => null, 'delta_pct' => null, 'direction' => null, 'tone' => 'neutral',
                'as_of' => null, 'observed_at' => null, 'is_demo' => false, 'points' => [],
            ];
        }

        $type = $spec['type'];
        $currency = $latest->currency;
        $delta = $latest->delta === null ? null : (float) $latest->delta;
        $direction = $delta === null ? null : ($delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'flat'));

        $from = $latest->period_date->copy()->subDays(self::SPARK_DAYS - 1)->toDateString();
        $points = $rows
            ->filter(fn (ComputedMetric $m) => $m->period_date->toDateString() >= $from && $m->currency === $currency)
            ->map(fn (ComputedMetric $m) => ['date' => $m->period_date->toDateString(), 'value' => (float) $m->value])
            ->values()
            ->all();

        return $base + [
            'state' => $latest->isStale($now) ? 'stale' : 'ok',
            'currency' => $currency,
            'value' => MetricFormat::value($latest->value, $type, $currency),
            'raw' => (float) $latest->value,
            'previous' => $latest->previous_value === null ? null : MetricFormat::value($latest->previous_value, $type, $currency),
            'delta' => $delta === null ? null : MetricFormat::delta($delta, $type, $currency),
            'delta_pct' => $latest->delta_pct === null ? null : MetricFormat::percent($latest->delta_pct),
            'direction' => $direction,
            'tone' => $this->tone($spec['good'], $direction),
            'as_of' => $latest->period_date->toDateString(),
            'observed_at' => $latest->observed_at?->toIso8601String(),
            'is_demo' => $latest->is_demo,
            'points' => $points,
        ];
    }

    private function tone(string $good, ?string $direction): string
    {
        if ($direction === null || $direction === 'flat' || $good === 'neutral') {
            return 'neutral';
        }

        return ($good === 'up') === ($direction === 'up') ? 'good' : 'bad';
    }

    /**
     * The metrics that have an open anomaly alert. The count of alerts is what the
     * headline says; this is the list of metrics they are about, newest first.
     *
     * @return list<string>
     */
    private function openAnomalyKeys(Team $team): array
    {
        return AnalyticsAlert::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('status', 'open')
            ->where('dedupe_key', 'like', 'anomaly:%')
            ->orderByDesc('triggered_at')
            ->get()
            ->map(fn (AnalyticsAlert $a) => $a->context['metric_key'] ?? null)
            ->filter()
            ->values()
            ->all();
    }

    private function headline(int $connected, int $reportingPlatforms, int $openAnomalies, int $stale): string
    {
        return match (true) {
            $connected === 0 => 'Nothing is reporting yet.',
            $openAnomalies > 0 => $openAnomalies === 1 ? '1 anomaly alert is open.' : "{$openAnomalies} anomaly alerts are open.",
            $stale > 0 => $stale === 1 ? '1 metric has gone quiet.' : "{$stale} metrics have gone quiet.",
            $reportingPlatforms === 0 => 'Connected, waiting for the first snapshot.',
            default => 'Everything reporting is current.',
        };
    }
}
