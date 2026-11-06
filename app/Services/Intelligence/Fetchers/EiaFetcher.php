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

/** US Energy Information Administration daily spot prices. Needs the free key from EIA_API_KEY. */
class EiaFetcher implements Fetcher
{
    public function __construct(private readonly SourceClient $client) {}

    public function fetch(Source $source, Collector $collector, array $cursor, Deadline $deadline): array
    {
        $key = config('intelligence.keys.eia');

        if (! $key) {
            throw new SourceFailed('No EIA key is set.');
        }

        $world = Geography::where('code', 'WLD')->value('code');

        foreach (Indicator::where('source_id', $source->id)->get() as $indicator) {
            $response = $this->client->get($source, 'https://api.eia.gov/v2/petroleum/pri/spt/data/', [
                'api_key' => $key, 'frequency' => 'daily', 'data[0]' => 'value', 'facets[series][]' => $indicator->source_code,
                'sort[0][column]' => 'period', 'sort[0][direction]' => 'desc', 'length' => 400,
            ]);

            foreach ($response->json('response.data') ?? [] as $row) {
                $collector->observation($indicator, (string) $world, (string) ($row['period'] ?? ''), $row['value'] ?? null);
            }
        }

        return [];
    }
}
