<?php

namespace App\Services\Metrics;

use App\Models\AnalyticsSnapshot;
use App\Models\ComputedMetric;
use App\Models\DataSource;
use App\Models\MetricDefinition;
use App\Models\Team;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;

/**
 * Turns a team's real, contract-validated snapshots into ComputedMetric rows:
 * one row per metric per day, carrying the value, the previous point it is
 * compared to, the delta and the delta percent.
 *
 * Honesty rules:
 *  - nothing is invented: a metric a platform never reported has no row, and a
 *    day the platform sent nothing has no row (no gap filling, no carry-forward);
 *  - a delta is only computed between two reported points in the same currency,
 *    and the percent is absent (not infinite) when the previous value was zero;
 *  - older, free-form snapshots (no taken_at) are never read;
 *  - idempotent: running it again with no new snapshot writes nothing.
 *
 * Staleness is not stored. It is derived when read (ComputedMetric::isStale), so
 * a value ages into stale by itself.
 */
class ComputeMetrics
{
    /** How far back one run recomputes. Older rows are left exactly as they were. */
    public const HISTORY_DAYS = 90;

    /** @var array<string, MetricDefinition> */
    private array $definitions = [];

    public function __construct(private readonly MetricCatalogue $catalogue = new MetricCatalogue) {}

    /**
     * @return array{metrics_written: int, metrics_seen: int, stale_metrics: int, platforms: int}
     */
    public function forTeam(Team $team, ?CarbonInterface $now = null): array
    {
        $now = $now ? Carbon::instance($now) : Carbon::now();
        $summary = ['metrics_written' => 0, 'metrics_seen' => 0, 'stale_metrics' => 0, 'platforms' => 0];

        $sources = DataSource::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('status', 'connected')
            ->whereIn('platform', $this->catalogue->platformKeys())
            ->get();

        foreach ($sources as $source) {
            $summary['platforms']++;

            foreach ($this->seriesFor($source, $now) as $key => $points) {
                $latest = $this->persist($team, $source->platform, $key, $points, $summary);

                if ($latest?->isStale($now)) {
                    $summary['stale_metrics']++;
                }
            }
        }

        return $summary;
    }

    /**
     * @return array<string, array{metrics_written: int, metrics_seen: int, stale_metrics: int, platforms: int}>
     */
    public function forAllTeams(): array
    {
        $out = [];

        foreach (Team::query()->orderBy('id')->get() as $team) {
            $out[$team->id] = $this->forTeam($team);
        }

        return $out;
    }

    /**
     * The daily points per metric key for one connected platform: the latest
     * snapshot of each UTC day wins.
     *
     * @return array<string, array<string, array{value: int|float, currency: string|null, snapshot: AnalyticsSnapshot}>>
     */
    private function seriesFor(DataSource $source, Carbon $now): array
    {
        $snapshots = AnalyticsSnapshot::withoutGlobalScopes()
            ->where('data_source_id', $source->id)
            ->whereNotNull('taken_at')
            ->where('period', 'day')
            ->where('taken_at', '>=', $now->copy()->subDays(self::HISTORY_DAYS)->startOfDay())
            ->orderBy('taken_at')
            ->orderBy('id')
            ->get();

        $perDay = [];
        foreach ($snapshots as $snapshot) {
            $perDay[$snapshot->taken_at->copy()->utc()->toDateString()] = $snapshot;
        }
        ksort($perDay);

        $series = [];
        foreach ($perDay as $date => $snapshot) {
            $currency = $snapshot->payload['currency'] ?? null;

            foreach (($snapshot->payload['metrics'] ?? []) as $key => $value) {
                if ($this->catalogue->metric($source->platform, (string) $key) === null || ! is_numeric($value)) {
                    continue;
                }

                $series[(string) $key][$date] = [
                    'value' => $value + 0,
                    'currency' => $this->catalogue->isMoney($source->platform, (string) $key) ? $currency : null,
                    'snapshot' => $snapshot,
                ];
            }
        }

        return $series;
    }

    /**
     * @param  array<string, array{value: int|float, currency: string|null, snapshot: AnalyticsSnapshot}>  $points  keyed by Y-m-d, ascending
     * @param  array{metrics_written: int, metrics_seen: int, stale_metrics: int, platforms: int}  $summary
     */
    private function persist(Team $team, string $platform, string $key, array $points, array &$summary): ?ComputedMetric
    {
        $definition = $this->definition($platform, $key);

        $existing = ComputedMetric::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('metric_definition_id', $definition->id)
            ->where('period', 'daily')
            ->whereIn('period_date', array_keys($points))
            ->get()
            ->keyBy(fn (ComputedMetric $m) => $m->period_date->toDateString());

        $previousDate = null;
        $previous = null;
        $latest = null;

        foreach ($points as $date => $point) {
            $summary['metrics_seen']++;

            $comparable = $previous !== null && $previous['currency'] === $point['currency'];
            $delta = $comparable ? round($point['value'] - $previous['value'], 4) : null;
            $pct = null;
            if ($comparable && (float) $previous['value'] !== 0.0) {
                $pct = round($delta / abs($previous['value']) * 100, 4);
                $pct = abs($pct) >= 1e7 ? null : $pct;
            }

            $attributes = [
                'value' => $point['value'],
                'previous_value' => $comparable ? $previous['value'] : null,
                'delta' => $delta,
                'delta_pct' => $pct,
                'previous_period_date' => $comparable ? $previousDate : null,
                'currency' => $point['currency'],
                'observed_at' => $point['snapshot']->taken_at,
                'snapshot_id' => $point['snapshot']->id,
                'is_demo' => $point['snapshot']->snapshot_type === SnapshotIngestService::TYPE_DEMO,
            ];

            $latest = $this->write($team, $definition, $date, $attributes, $existing->get($date), $summary);

            $previousDate = $date;
            $previous = $point;
        }

        return $latest;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array{metrics_written: int, metrics_seen: int, stale_metrics: int, platforms: int}  $summary
     */
    private function write(Team $team, MetricDefinition $definition, string $date, array $attributes, ?ComputedMetric $row, array &$summary): ComputedMetric
    {
        if ($row === null) {
            try {
                $row = ComputedMetric::withoutGlobalScopes()->create($attributes + [
                    'team_id' => $team->id,
                    'metric_definition_id' => $definition->id,
                    'period' => 'daily',
                    'period_date' => $date,
                ]);
                $summary['metrics_written']++;

                return $row;
            } catch (UniqueConstraintViolationException) {
                // The post-ingest job and the hourly run raced; update what won.
                $row = ComputedMetric::withoutGlobalScopes()
                    ->where('team_id', $team->id)
                    ->where('metric_definition_id', $definition->id)
                    ->where('period', 'daily')
                    ->whereDate('period_date', $date)
                    ->firstOrFail();
            }
        }

        if ($this->differs($row, $attributes)) {
            $row->forceFill($attributes)->save();
            $summary['metrics_written']++;
        }

        return $row;
    }

    /** @param  array<string, mixed>  $attributes */
    private function differs(ComputedMetric $row, array $attributes): bool
    {
        foreach (['value', 'previous_value', 'delta', 'delta_pct'] as $field) {
            $old = $row->{$field};
            $new = $attributes[$field];

            if (($old === null) !== ($new === null)) {
                return true;
            }
            if ($old !== null && abs((float) $old - (float) $new) > 0.00005) {
                return true;
            }
        }

        $date = fn ($d) => $d === null ? null : Carbon::parse($d)->toDateString();

        return $date($row->previous_period_date) !== $date($attributes['previous_period_date'])
            || $row->currency !== $attributes['currency']
            || $row->observed_at?->getTimestamp() !== Carbon::parse($attributes['observed_at'])->getTimestamp()
            || $row->snapshot_id !== $attributes['snapshot_id']
            || $row->is_demo !== $attributes['is_demo'];
    }

    private function definition(string $platform, string $key): MetricDefinition
    {
        $fullKey = $this->catalogue->fullKey($platform, $key);

        return $this->definitions[$fullKey] ??= MetricDefinition::updateOrCreate(
            ['key' => $fullKey],
            $this->catalogue->definitionAttributes($platform, $key),
        );
    }
}
