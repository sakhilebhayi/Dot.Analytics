<?php

namespace App\Services\Intelligence\Reading;

use App\Models\Intelligence\DataIssue;
use App\Models\Intelligence\Geography;
use App\Models\Intelligence\Indicator;
use App\Models\Intelligence\Industry;
use App\Models\Intelligence\Item;
use App\Models\Intelligence\Observation;
use App\Models\Intelligence\ObservationRevision;
use App\Models\Intelligence\Signal;
use App\Models\Intelligence\Source;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Everything the public pages read. It only reads, never writes, and only published series: a series under a licence we
 * cannot use is invisible here. A figure with no stored value is absent, never filled in.
 */
class Reader
{
    public const SERIES_POINTS = 60;

    /** @var array<int, Source> */
    private array $sources = [];

    private function sourceOf(Indicator $indicator): Source
    {
        return $this->sources[$indicator->source_id] ??= Source::findOrFail($indicator->source_id);
    }

    public function indicatorByKey(string $key): ?Indicator
    {
        return Indicator::where('key', $key)->where('is_published', true)->first();
    }

    public function geographyByCode(string $code): ?Geography
    {
        return Geography::where('code', $code)->first();
    }

    public function figure(Indicator $indicator, Geography $geography, int $points = self::SERIES_POINTS): ?FigureData
    {
        return $this->figuresFor(collect([$indicator]), $geography, $points)[$indicator->id] ?? null;
    }

    /**
     * Figures for several series in one place, in two queries however many series there are: one for the readings and one
     * for their revisions. A series with no reading is absent.
     *
     * @param  Collection<int, Indicator>  $indicators
     * @return array<int, FigureData> by indicator id
     */
    public function figuresFor(Collection $indicators, Geography $geography, int $points = self::SERIES_POINTS, bool $revisions = true): array
    {
        $indicators = $indicators->filter(fn (Indicator $i) => $i->is_published)->keyBy('id');

        if ($indicators->isEmpty()) {
            return [];
        }

        $rows = Observation::whereIn('indicator_id', $indicators->keys())->where('geography_id', $geography->id)
            ->orderByDesc('period')->get(['id', 'indicator_id', 'period', 'value', 'last_seen_at'])->groupBy('indicator_id')
            ->map(fn ($g) => $g->take($points)->reverse()->values());

        $changes = $revisions && $rows->isNotEmpty()
            ? ObservationRevision::whereIn('observation_id', $rows->flatten(1)->pluck('id'))->orderByDesc('revised_at')->get()->groupBy('observation_id')
            : collect();

        $out = [];

        foreach ($rows as $id => $series) {
            $indicator = $indicators[$id];
            $notes = $series->flatMap(fn ($o) => ($changes[$o->id] ?? collect())->map(fn ($r) => ['period' => $o->period->toDateString(), 'previous' => $r->previous_value, 'now' => $r->new_value, 'revised_at' => $r->revised_at->toDateString(), 'at' => $r->revised_at]))
                ->sortByDesc('at')->take(10)->map(fn ($n) => array_diff_key($n, ['at' => 1]))->values()->all();

            $out[$id] = new FigureData(
                $indicator, $geography, $this->sourceOf($indicator),
                $series->map(fn ($o) => ['period' => $o->period, 'value' => (float) $o->value])->all(),
                $notes, $series->max('last_seen_at'),
            );
        }

        return $out;
    }

    /** @return list<FigureData> */
    public function keyFigures(int $limit = 6): array
    {
        $wanted = collect((array) config('intelligence_home.figures'))->map(fn ($pair) => ['key' => $pair[0], 'code' => $pair[1]]);
        $indicators = Indicator::whereIn('key', $wanted->pluck('key'))->where('is_published', true)->get()->keyBy('key');
        $places = Geography::whereIn('code', $wanted->pluck('code'))->get()->keyBy('code');

        $byPlace = [];

        foreach ($wanted as $w) {
            if (isset($indicators[$w['key']], $places[$w['code']])) {
                $byPlace[$w['code']][] = $indicators[$w['key']];
            }
        }

        $found = [];

        foreach ($byPlace as $code => $list) {
            $found[$code] = $this->figuresFor(collect($list), $places[$code], 24);
        }

        $figures = [];

        foreach ($wanted as $w) {
            $figure = isset($indicators[$w['key']]) ? ($found[$w['code']][$indicators[$w['key']]->id] ?? null) : null;

            if ($figure !== null && count($figures) < $limit) {
                $figures[] = $figure;
            }
        }

        return $figures;
    }

    /**
     * Every published series for an industry, as figures for one place.
     *
     * @return list<FigureData>
     */
    public function industryFigures(Industry $industry, Geography $geography): array
    {
        $indicators = $industry->indicators()->where('is_published', true)->orderBy('name')->get();
        $figures = $this->figuresFor($indicators, $geography, 24);

        return $indicators->map(fn (Indicator $i) => $figures[$i->id] ?? null)->filter()->values()->all();
    }

    /** @return array<string, list<FigureData>> by topic */
    public function countryFigures(Geography $geography): array
    {
        $indicatorIds = Observation::where('geography_id', $geography->id)->distinct()->pluck('indicator_id');

        $indicators = Indicator::whereIn('id', $indicatorIds)->where('is_published', true)->orderBy('topic')->orderBy('name')->get();
        $figures = $this->figuresFor($indicators, $geography, 24);

        return $indicators->map(fn (Indicator $i) => $figures[$i->id] ?? null)->filter()
            ->groupBy(fn (FigureData $f) => $f->indicator->topic)->map(fn ($g) => $g->values()->all())->all();
    }

    /**
     * What a page has and has not got: one cell per series that applies, with how fresh it is. The gaps are the point.
     *
     * @param  Collection<int, Indicator>  $indicators
     * @return list<array{indicator: Indicator, freshness: Freshness, period: ?string}>
     */
    public function coverage(Collection $indicators, Geography $geography): array
    {
        $latest = Observation::where('geography_id', $geography->id)->whereIn('indicator_id', $indicators->pluck('id'))
            ->selectRaw('indicator_id, max(period) as period')->groupBy('indicator_id')->pluck('period', 'indicator_id');

        return $indicators->map(function (Indicator $i) use ($latest) {
            $period = $latest[$i->id] ?? null;

            if ($period === null) {
                return ['indicator' => $i, 'freshness' => Freshness::Missing, 'period' => null];
            }

            $at = Carbon::parse($period);

            return ['indicator' => $i, 'freshness' => $at->diffInDays(now()) > $i->stale_after_days ? Freshness::Stale : Freshness::Current, 'period' => Format::period($at, $i->frequency)];
        })->values()->all();
    }

    /** A one-line grade for an industry or place from its coverage cells. Honest, not flattering. */
    public function grade(array $cells): string
    {
        $current = count(array_filter($cells, fn ($c) => $c['freshness'] === Freshness::Current));
        $total = count($cells);

        return match (true) {
            $total === 0 || $current === 0 => 'Thin',
            $current / $total >= 0.6 && $current >= 5 => 'Strong',
            $current >= 3 => 'Moderate',
            default => 'Thin',
        };
    }

    /**
     * Newest developments, optionally for an industry and a place.
     *
     * @return Collection<int, Item>
     */
    public function items(int $limit = 12, ?Industry $industry = null, ?Geography $geography = null, ?string $kind = null): Collection
    {
        return Item::listed()->with('source:id,name,key,attribution,allows_summary')
            ->when($industry, fn (Builder $q) => $q->whereHas('industries', fn ($i) => $i->where('industries.id', $industry->id)))
            ->when($geography, fn (Builder $q) => $q->whereHas('geographies', fn ($g) => $g->where('geographies.id', $geography->id)))
            ->when($kind, fn (Builder $q) => $q->where('kind', $kind))
            ->orderByDesc('published_at')->limit($limit)->get();
    }

    /** @return Collection<int, Signal> */
    public function signals(int $limit = 5): Collection
    {
        return Signal::with(['indicator.source', 'geography'])->where('detected_at', '>=', now()->subDays(45))
            ->whereHas('indicator', fn ($q) => $q->where('is_published', true))
            ->orderByDesc('period')->orderByRaw('abs(size) desc')->limit($limit)->get();
    }

    /**
     * Latest values of a few measures for many places, one query each.
     *
     * @param  list<string>  $indicatorKeys
     * @param  list<string>  $codes
     * @return array{rows: list<array{geography: Geography, cells: array<string, ?FigureData>}>, indicators: Collection<int, Indicator>}
     */
    public function matrix(array $indicatorKeys, array $codes): array
    {
        $geographies = Geography::whereIn('code', $codes)->get()->keyBy('code');
        $indicators = Indicator::whereIn('key', $indicatorKeys)->where('is_published', true)->get()->sortBy(fn ($i) => array_search($i->key, $indicatorKeys))->values();

        // One query for every cell: the latest three readings of each series for each country, cut apart here.
        $rows = Observation::whereIn('indicator_id', $indicators->pluck('id'))->whereIn('geography_id', $geographies->pluck('id'))
            ->where('period', '>=', now()->subYears(8)->toDateString())->orderByDesc('period')
            ->get(['indicator_id', 'geography_id', 'period', 'value', 'last_seen_at'])->groupBy(fn ($o) => $o->geography_id.'|'.$o->indicator_id);

        $cells = [];

        foreach ($geographies as $geography) {
            foreach ($indicators as $indicator) {
                $series = ($rows[$geography->id.'|'.$indicator->id] ?? collect())->take(3)->reverse()->values();
                $cells[$geography->code][$indicator->key] = $series->isEmpty() ? null : new FigureData(
                    $indicator, $geography, $this->sourceOf($indicator),
                    $series->map(fn ($o) => ['period' => $o->period, 'value' => (float) $o->value])->all(), [], $series->max('last_seen_at'),
                );
            }
        }

        $rowsOut = collect($codes)->filter(fn ($c) => isset($geographies[$c]))
            ->map(fn ($c) => ['geography' => $geographies[$c], 'cells' => $cells[$c] ?? []])->values()->all();

        return ['rows' => $rowsOut, 'indicators' => $indicators];
    }

    /**
     * Coverage for every industry for one place, in two queries however many industries there are.
     *
     * @param  Collection<int, Industry>  $industries
     * @return array<string, array{cells: list<array<string, mixed>>, grade: string}> by industry slug
     */
    public function industryCoverage(Collection $industries, ?Geography $geography): array
    {
        if ($geography === null) {
            return $industries->mapWithKeys(fn (Industry $i) => [$i->slug => ['cells' => [], 'grade' => 'Thin']])->all();
        }

        $indicators = Indicator::where('is_published', true)->with('industries:id')->orderBy('name')->get();
        $cells = collect($this->coverage($indicators, $geography))->keyBy(fn ($c) => $c['indicator']->id);

        return $industries->mapWithKeys(function (Industry $industry) use ($indicators, $cells) {
            $mine = $indicators->filter(fn (Indicator $i) => $i->industries->contains('id', $industry->id))
                ->map(fn (Indicator $i) => $cells[$i->id])->values()->all();

            return [$industry->slug => ['cells' => $mine, 'grade' => $this->grade($mine)]];
        })->all();
    }

    /** @return array<string, int|string|null> what the library holds, for the sources page and the front page footer */
    public function holdings(): array
    {
        return [
            'sources_collecting' => Source::where('is_enabled', true)->count(),
            'sources_waiting' => Source::where('status', 'waiting')->count(),
            'series' => Indicator::where('is_published', true)->whereHas('observations')->count(),
            'observations' => Observation::count(),
            'items' => Item::listed()->count(),
            'last_collection' => Source::max('last_success_at'),
            'open_issues' => DataIssue::whereNull('resolved_at')->count(),
        ];
    }
}
