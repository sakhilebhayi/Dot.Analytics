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
        if (! $indicator->is_published) {
            return null;
        }

        $rows = Observation::where('indicator_id', $indicator->id)->where('geography_id', $geography->id)
            ->orderByDesc('period')->limit($points)->get(['id', 'period', 'value', 'last_seen_at'])->reverse()->values();

        if ($rows->isEmpty()) {
            return null;
        }

        $revisions = ObservationRevision::whereIn('observation_id', $rows->pluck('id'))->orderByDesc('revised_at')->limit(10)->get()
            ->map(fn ($r) => ['period' => $rows->firstWhere('id', $r->observation_id)?->period->toDateString() ?? '', 'previous' => $r->previous_value, 'now' => $r->new_value, 'revised_at' => $r->revised_at->toDateString()])->all();

        return new FigureData(
            $indicator, $geography, $indicator->source,
            $rows->map(fn ($o) => ['period' => $o->period, 'value' => (float) $o->value])->all(),
            $revisions, $rows->max('last_seen_at'),
        );
    }

    /** @return list<FigureData> */
    public function keyFigures(int $limit = 6): array
    {
        $figures = [];

        foreach ((array) config('intelligence_home.figures') as [$key, $code]) {
            if (count($figures) >= $limit) {
                break;
            }

            $indicator = $this->indicatorByKey($key);
            $geography = $this->geographyByCode($code);
            $figure = $indicator && $geography ? $this->figure($indicator, $geography, 24) : null;

            if ($figure !== null) {
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
        return $industry->indicators()->where('is_published', true)->orderBy('name')->get()
            ->map(fn (Indicator $i) => $this->figure($i, $geography, 24))->filter()->values()->all();
    }

    /** @return array<string, list<FigureData>> by topic */
    public function countryFigures(Geography $geography): array
    {
        $indicatorIds = Observation::where('geography_id', $geography->id)->distinct()->pluck('indicator_id');

        return Indicator::whereIn('id', $indicatorIds)->where('is_published', true)->orderBy('topic')->orderBy('name')->get()
            ->map(fn (Indicator $i) => $this->figure($i, $geography, 24))->filter()
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
        $cells = [];

        foreach ($indicators as $indicator) {
            foreach ($geographies as $geography) {
                $cells[$geography->code][$indicator->key] = $this->figure($indicator, $geography, 3);
            }
        }

        $rows = collect($codes)->filter(fn ($c) => isset($geographies[$c]))
            ->map(fn ($c) => ['geography' => $geographies[$c], 'cells' => $cells[$c] ?? []])->values()->all();

        return ['rows' => $rows, 'indicators' => $indicators];
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
