<?php

namespace App\Services\Metrics;

/**
 * Read access to config/metrics.php, the snapshot contract's catalogue of which
 * metrics each real Dot platform may report.
 *
 * @phpstan-type MetricSpec array{label: string, type: string, good: string}
 */
class MetricCatalogue
{
    /** The key every money metric's currency travels under. Not a metric itself. */
    public const CURRENCY_KEY = 'currency';

    /** @return array<string, array{label: string, engine: string, metrics: array<string, array<string, mixed>>}> */
    public function platforms(): array
    {
        return config('metrics.platforms', []);
    }

    /** @return list<string> */
    public function platformKeys(): array
    {
        return array_keys($this->platforms());
    }

    public function hasPlatform(string $platform): bool
    {
        return isset($this->platforms()[$platform]);
    }

    /** @return array<string, mixed>|null */
    public function platform(string $platform): ?array
    {
        return $this->platforms()[$platform] ?? null;
    }

    /** @return array<string, array<string, mixed>> */
    public function metrics(string $platform): array
    {
        return $this->platforms()[$platform]['metrics'] ?? [];
    }

    /** @return array<string, mixed>|null */
    public function metric(string $platform, string $key): ?array
    {
        return $this->metrics($platform)[$key] ?? null;
    }

    /** @return array<string, mixed> */
    public function type(string $type): array
    {
        return config("metrics.types.{$type}", []);
    }

    public function isMoney(string $platform, string $key): bool
    {
        $metric = $this->metric($platform, $key);

        return $metric !== null && ($this->type($metric['type'])['money'] ?? false) === true;
    }

    /** "tasks.overdue": the stable id of a metric across definitions, widgets and alerts. */
    public function fullKey(string $platform, string $key): string
    {
        return "{$platform}.{$key}";
    }

    /** @return array{platform: string, metric: string}|null */
    public function parse(string $fullKey): ?array
    {
        [$platform, $metric] = array_pad(explode('.', $fullKey, 2), 2, '');

        return $this->metric($platform, $metric) === null ? null : ['platform' => $platform, 'metric' => $metric];
    }

    /** @return list<string> */
    public function periods(): array
    {
        return config('metrics.periods', ['day']);
    }

    /**
     * The metric_definitions row for a catalogue metric.
     *
     * @return array{label: string, source_platform: string, engine: string, aggregation: string, unit: string|null, description: string}
     */
    public function definitionAttributes(string $platform, string $key): array
    {
        $spec = $this->metric($platform, $key) ?? [];
        $type = $spec['type'] ?? null;
        $platformLabel = $this->platform($platform)['label'] ?? $platform;

        return [
            'label' => $spec['label'] ?? $key,
            'source_platform' => $platform,
            'engine' => $this->platform($platform)['engine'] ?? 'data',
            'aggregation' => 'latest',
            'unit' => $type,
            'description' => "{$platformLabel} reports this in its daily snapshot ({$type}).",
        ];
    }
}
