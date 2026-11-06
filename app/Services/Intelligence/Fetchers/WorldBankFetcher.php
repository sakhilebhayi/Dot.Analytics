<?php

namespace App\Services\Intelligence\Fetchers;

use App\Models\Intelligence\Geography;
use App\Models\Intelligence\Indicator;
use App\Models\Intelligence\Source;
use App\Services\Intelligence\Collector;
use App\Services\Intelligence\Deadline;
use App\Services\Intelligence\Fetcher;
use App\Services\Intelligence\SourceClient;
use App\Services\Intelligence\SourceFailed;

/**
 * World Bank Indicators. One request per series, for every country we cover. Before a series is first published its
 * own licence is read from the World Bank's metadata: only series marked CC BY are published, because some in the same
 * database belong to other organisations with other terms.
 */
class WorldBankFetcher implements Fetcher
{
    private const API = 'https://api.worldbank.org/v2';

    public function __construct(private readonly SourceClient $client, private readonly DataIssues $issues) {}

    public function fetch(Source $source, Collector $collector, array $cursor, Deadline $deadline): array
    {
        $indicators = Indicator::where('source_id', $source->id)->orderBy('id')->get();
        $codes = Geography::where('kind', '!=', 'region')->orderBy('code')->pluck('code')->implode(';');
        $position = (int) ($cursor['position'] ?? 0);

        for (; $position < $indicators->count(); $position++) {
            if ($deadline->passed()) {
                return ['position' => $position, 'more' => true];
            }

            $this->collect($source, $indicators[$position], $collector, $codes);
        }

        return ['position' => 0, 'more' => false];
    }

    private function collect(Source $source, Indicator $indicator, Collector $collector, string $countries): void
    {
        if (! $this->licenceAllows($source, $indicator)) {
            return;
        }

        $response = $this->client->get($source, self::API."/country/{$countries}/indicator/{$indicator->source_code}", [
            'format' => 'json', 'per_page' => 20000, 'date' => '1990:'.(now()->year + 1),
        ]);

        $body = $response->json();

        if (! is_array($body) || ! isset($body[1]) || ! is_array($body[1])) {
            throw new SourceFailed('The World Bank answered in a shape we do not read for '.$indicator->source_code.'.');
        }

        foreach ($body[1] as $row) {
            $period = isset($row['date']) && preg_match('/^\d{4}$/', (string) $row['date']) ? $row['date'].'-01-01' : '';

            if ($row['value'] === null) {
                continue;
            }

            $collector->observation($indicator, (string) ($row['countryiso3code'] ?? ''), $period, $row['value']);
        }
    }

    /** Reads the series' licence once a month. Anything but CC BY is left unpublished and raised as an issue. */
    private function licenceAllows(Source $source, Indicator $indicator): bool
    {
        if ($indicator->licence_checked_at !== null && $indicator->licence_checked_at->gt(now()->subDays(30))) {
            return $indicator->is_published;
        }

        $response = $this->client->get($source, self::API.'/sources/2/series/'.$indicator->source_code.'/metadata', ['format' => 'json']);
        $meta = collect($response->json('source.0.concept.0.variable.0.metatype') ?? [])->mapWithKeys(fn ($m) => [$m['id'] => $m['value']]);

        $licence = (string) ($meta['License_Type'] ?? '');
        $open = str_starts_with(strtoupper($licence), 'CC BY') && ! str_contains(strtoupper($licence), 'NC');

        $update = ['licence' => $licence ?: null, 'licence_url' => $meta['License_URL'] ?? null, 'is_published' => $open, 'licence_checked_at' => now()];

        if ($open) {
            $update['definition'] = $this->definition($meta->toArray(), $indicator);
        }

        $indicator->forceFill($update)->save();

        if (! $open) {
            $this->issues->raise('licence', 'high', "{$indicator->name} ({$indicator->source_code}) is not under an open licence and is not shown.");
        }

        return $open;
    }

    /** @param  array<string, mixed>  $meta */
    private function definition(array $meta, Indicator $indicator): string
    {
        $text = trim((string) ($meta['Shortdefinition'] ?? $meta['Longdefinition'] ?? ''));

        return $text === '' ? $indicator->definition : mb_substr($text, 0, 1500);
    }
}
