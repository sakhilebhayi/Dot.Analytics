<?php

namespace App\Services\Intelligence\Fetchers;

use App\Models\Intelligence\Geography;
use App\Models\Intelligence\Indicator;
use App\Models\Intelligence\Source;
use App\Services\Intelligence\Collector;
use App\Services\Intelligence\Deadline;
use App\Services\Intelligence\Fetcher;
use App\Services\Intelligence\SourceClient;
use Carbon\CarbonImmutable;

/**
 * Any source that serves statistical series as CSV with a period and a value column: ECB, BIS, OECD, ILO. Each indicator
 * names its own query, place and the row it wants, so a new series is data, not code.
 */
class SdmxCsvFetcher implements Fetcher
{
    public function __construct(private readonly SourceClient $client) {}

    public function fetch(Source $source, Collector $collector, array $cursor, Deadline $deadline): array
    {
        $indicators = Indicator::where('source_id', $source->id)->orderBy('id')->get();
        $position = (int) ($cursor['position'] ?? 0);

        for (; $position < $indicators->count(); $position++) {
            if ($deadline->passed()) {
                return ['position' => $position, 'more' => true];
            }

            $this->collect($source, $indicators[$position], $collector);
        }

        return ['position' => 0, 'more' => false];
    }

    private function collect(Source $source, Indicator $indicator, Collector $collector): void
    {
        $query = (array) ($indicator->settings ?? []);
        $geography = Geography::where('code', $query['geography'] ?? '')->first();

        if ($geography === null || empty($query['url'])) {
            return;
        }

        $response = $this->client->get($source, $query['url'], (array) ($query['params'] ?? []));
        $rows = $this->rows($response->body());
        $match = (array) ($query['match'] ?? []);

        foreach ($rows as $row) {
            if (array_diff_assoc($match, array_intersect_key($row, $match)) !== []) {
                continue;
            }

            $period = $this->period((string) ($row['TIME_PERIOD'] ?? ''));
            $value = $row['OBS_VALUE'] ?? '';

            if ($period === null || $value === '' || strtoupper((string) $value) === 'NAN') {
                continue;
            }

            $collector->observation($indicator, $geography->code, $period, $value);
        }
    }

    /** @return list<array<string, string>> */
    private function rows(string $csv): array
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, preg_replace('/^\xEF\xBB\xBF/', '', $csv));
        rewind($handle);
        $header = fgetcsv($handle, 0, ',', '"', '');
        $rows = [];

        while ($header !== false && ($line = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            if (count($line) === count($header)) {
                $rows[] = array_combine($header, $line);
            }
        }

        fclose($handle);

        return $rows;
    }

    /** Day, month, quarter and year periods all become the first day of the period. */
    private function period(string $value): ?string
    {
        return match (true) {
            (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) => $value,
            (bool) preg_match('/^\d{4}-\d{2}$/', $value) => $value.'-01',
            (bool) preg_match('/^(\d{4})-Q([1-4])$/', $value, $m) => CarbonImmutable::create((int) $m[1], ((int) $m[2] - 1) * 3 + 1, 1)->toDateString(),
            (bool) preg_match('/^\d{4}$/', $value) => $value.'-01-01',
            default => null,
        };
    }
}
