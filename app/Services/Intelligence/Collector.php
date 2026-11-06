<?php

namespace App\Services\Intelligence;

use App\Models\Intelligence\Geography;
use App\Models\Intelligence\Indicator;
use App\Models\Intelligence\Item;
use App\Models\Intelligence\Observation;
use App\Models\Intelligence\ObservationRevision;
use App\Models\Intelligence\Source;
use App\Models\Intelligence\SourceRun;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Takes what a fetcher found and stores it, in short batches so a page is never kept waiting. Running the same data
 * twice changes nothing. A changed value keeps its old value on record. Anything malformed is counted and left out.
 */
class Collector
{
    private const BATCH = 500;

    /** @var list<array{indicator: int, geography: int, period: string, value: float}> */
    private array $buffer = [];

    /** @var array<string, int|null> */
    private array $geographies = [];

    /** @var array<string, int> */
    public array $counts = ['fetched' => 0, 'created' => 0, 'updated' => 0, 'unchanged' => 0, 'rejected' => 0];

    public function __construct(private readonly Source $source, private readonly SourceRun $run) {}

    public function observation(Indicator $indicator, string $geographyCode, string $period, mixed $value): void
    {
        $this->counts['fetched']++;
        $geography = $this->geographyId($geographyCode);

        if ($geography === null || ! is_numeric($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $period) || ! is_finite((float) $value)) {
            $this->counts['rejected']++;

            return;
        }

        $this->buffer[] = ['indicator' => $indicator->id, 'geography' => $geography, 'period' => $period, 'value' => (float) $value];

        if (count($this->buffer) >= self::BATCH) {
            $this->flush();
        }
    }

    /**
     * A development, by its link to the original. The headline, date and publisher are facts; the summary is stored
     * only where the source's terms allow it.
     */
    public function item(string $kind, string $title, string $url, ?Carbon $publishedAt, ?string $summary = null, ?string $externalId = null): ?Item
    {
        $this->counts['fetched']++;
        $title = $this->clean($title);
        $canonical = $this->canonical($url);

        if ($title === '' || $canonical === null || $publishedAt === null || $publishedAt->gt(now()->addDay())) {
            $this->counts['rejected']++;

            return null;
        }

        $urlHash = hash('sha256', $canonical);
        $titleHash = hash('sha256', Str::lower((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $title)));

        if (Item::where('url_hash', $urlHash)->exists()) {
            $this->counts['unchanged']++;

            return null;
        }

        // The same headline from another feed within a few days is one development, not two.
        $original = Item::where('title_hash', $titleHash)->whereNull('duplicate_of')
            ->whereBetween('published_at', [$publishedAt->copy()->subDays(3), $publishedAt->copy()->addDays(3)])->value('id');

        $item = Item::create([
            'uuid' => (string) Str::uuid(),
            'source_id' => $this->source->id,
            'kind' => $kind,
            'title' => Str::limit($title, 480, ''),
            'source_summary' => $this->source->allows_summary && $summary !== null ? (Str::limit($this->clean($summary), 600) ?: null) : null,
            'url' => $canonical,
            'url_hash' => $urlHash,
            'title_hash' => $titleHash,
            'external_id' => $externalId,
            'published_at' => $publishedAt,
            'collected_at' => now(),
            'duplicate_of' => $original,
        ]);

        $this->counts['created']++;

        return $item;
    }

    public function flush(): void
    {
        foreach (array_chunk($this->buffer, self::BATCH) as $batch) {
            DB::connection(config('intelligence.connection'))->transaction(fn () => $this->store($batch));
        }

        $this->buffer = [];
    }

    /** @param  list<array{indicator: int, geography: int, period: string, value: float}>  $batch */
    private function store(array $batch): void
    {
        $now = now();

        foreach ($batch as $row) {
            $existing = Observation::where('indicator_id', $row['indicator'])->where('geography_id', $row['geography'])->where('period', $row['period'])->first();

            if ($existing === null) {
                Observation::create([
                    'indicator_id' => $row['indicator'], 'geography_id' => $row['geography'], 'period' => $row['period'], 'value' => $row['value'],
                    'source_run_id' => $this->run->id, 'first_seen_at' => $now, 'last_seen_at' => $now,
                ]);
                $this->counts['created']++;
            } elseif (abs($existing->value - $row['value']) > 1e-9) {
                ObservationRevision::create(['observation_id' => $existing->id, 'previous_value' => $existing->value, 'new_value' => $row['value'], 'source_run_id' => $this->run->id, 'revised_at' => $now]);
                $existing->update(['value' => $row['value'], 'source_run_id' => $this->run->id, 'last_seen_at' => $now]);
                $this->counts['updated']++;
            } else {
                $existing->update(['last_seen_at' => $now]);
                $this->counts['unchanged']++;
            }
        }
    }

    private function geographyId(string $code): ?int
    {
        return $this->geographies[$code] ??= Geography::where('code', $code)->value('id');
    }

    private function clean(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    /** One address for one document, whatever tracking was added to it. Only https links are kept. */
    private function canonical(string $url): ?string
    {
        $parts = parse_url(trim($url));

        if (! in_array($parts['scheme'] ?? '', ['https', 'http'], true) || empty($parts['host'])) {
            return null;
        }

        parse_str($parts['query'] ?? '', $query);
        $query = array_filter($query, fn ($key) => ! preg_match('/^(utm_|ref$|cmp$|at_|fbclid|gclid)/i', (string) $key), ARRAY_FILTER_USE_KEY);
        ksort($query);

        return 'https://'.strtolower($parts['host']).rtrim($parts['path'] ?? '', '/').($query === [] ? '' : '?'.http_build_query($query));
    }
}
