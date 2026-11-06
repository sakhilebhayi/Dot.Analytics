<?php

namespace App\Services\Intelligence\Search;

use App\Models\Intelligence\Geography;
use App\Models\Intelligence\Indicator;
use App\Services\Intelligence\Reading\FigureData;
use App\Services\Intelligence\Reading\Freshness;
use App\Services\Intelligence\Reading\Reader;
use Illuminate\Support\Collection;

/**
 * Answers a question from stored figures and releases only, by template. No model writes any of it, so an answer can
 * only say what a source said, and where we hold nothing it says that instead. Every line carries its source and period
 * and links to the figure behind it.
 */
class Answerer
{
    /** The measures shown when a question names a place or an industry but no measure. */
    private const HEADLINE = ['wb.gdp-growth', 'wb.inflation', 'wb.unemployment'];

    public function __construct(private readonly Reader $reader) {}

    /**
     * @return array{title: string, lines: list<array{text: string, href: string, source: string, period: string, stale: bool}>, missing: list<string>, items: Collection, grounded: bool}
     */
    public function answer(Question $q): array
    {
        $place = $q->place ?? $this->reader->geographyByCode('ZAF');
        $figures = $place === null ? [] : $this->figures($q, $place);
        $lines = array_map(fn (FigureData $f) => $this->line($f), $figures);

        return [
            'title' => $this->title($q, $place, count($lines)),
            'lines' => $lines,
            'missing' => $this->missing($q, $place, $figures),
            'items' => $this->reader->items(5, $q->industry, $q->place),
            'grounded' => $lines !== [],
        ];
    }

    /** @return list<FigureData> */
    private function figures(Question $q, Geography $place): array
    {
        $indicators = match (true) {
            $q->measureKeys !== [] => Indicator::whereIn('key', $q->measureKeys)->get(),
            $q->industry !== null => $q->industry->indicators()->get(),
            default => Indicator::whereIn('key', self::HEADLINE)->get(),
        };

        $world = $this->reader->geographyByCode('WLD');
        $out = [];

        foreach ($indicators as $i) {
            // A world series (oil) answers for any place; every other series answers for the place asked about.
            $where = ($i->settings['geography'] ?? null) === 'WLD' ? $world : $place;
            $figure = $where === null ? null : $this->reader->figure($i, $where, 24);

            if ($figure !== null) {
                $out[] = $figure;
            }
        }

        return $out;
    }

    /** @return array{text: string, href: string, source: string, period: string, stale: bool} */
    private function line(FigureData $f): array
    {
        $change = $f->change();

        return [
            'text' => $f->indicator->name.(str_contains($f->indicator->name, $f->geography->name) ? '' : ' in '.$f->geography->name).': '.$f->value().' '.$f->note().'.'.($change ? ' '.$change['words'].'.' : ''),
            'href' => route('intel.indicator', [$f->indicator, 'place' => $f->geography->code]),
            'source' => $f->source->name,
            'period' => (string) $f->period(),
            'stale' => $f->freshness() === Freshness::Stale,
        ];
    }

    /**
     * What we looked for and do not have.
     *
     * @param  list<FigureData>  $figures
     * @return list<string>
     */
    private function missing(Question $q, ?Geography $place, array $figures): array
    {
        $missing = [];

        if ($q->industry !== null && $place !== null) {
            $cells = $this->reader->coverage($q->industry->indicators()->get(), $place);
            $gone = count(array_filter($cells, fn ($c) => $c['freshness'] !== Freshness::Current));

            if ($cells === []) {
                $missing[] = "We hold no series for {$q->industry->name} yet.";
            } elseif ($gone > 0) {
                $missing[] = "{$gone} of the ".count($cells)." series we list for {$q->industry->name} in {$place->name} are out of date or not available.";
            }

            if ($place->code !== 'ZAF') {
                $missing[] = "Monthly industry figures exist for South Africa only. For {$place->name} we hold annual national figures.";
            }
        }

        if ($figures === []) {
            $missing[] = 'We hold no stored figures that answer this question.';
        }

        return $missing;
    }

    private function title(Question $q, ?Geography $place, int $count): string
    {
        if ($count === 0) {
            return 'We do not have figures that answer that.';
        }

        $about = collect([$q->industry?->name, $place?->name])->filter()->implode(' in ');

        return "What we hold on {$about}";
    }
}
