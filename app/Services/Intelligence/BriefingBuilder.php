<?php

namespace App\Services\Intelligence;

use App\Models\Intelligence\Briefing;
use App\Models\Intelligence\Item;
use App\Models\Intelligence\Signal;
use App\Services\Intelligence\Reading\FigureData;
use App\Services\Intelligence\Reading\Reader;

/**
 * Writes the day's editions from what is stored, by template. No model writes a word of them, so every sentence can be
 * traced to a figure or a release, and an edition with nothing to say is simply not published.
 */
class BriefingBuilder
{
    public function __construct(private readonly Reader $reader) {}

    /** Builds or refreshes today's editions. Returns how many are published. */
    public function refreshToday(): int
    {
        $published = 0;

        foreach (array_keys((array) config('intelligence_briefings.editions')) as $key) {
            $published += $this->build($key) !== null ? 1 : 0;
        }

        return $published;
    }

    public function build(string $edition): ?Briefing
    {
        $def = config("intelligence_briefings.editions.{$edition}");

        if ($def === null) {
            return null;
        }

        $sections = array_values(array_filter([
            $this->releases($def),
            $this->figures($def),
            $this->movements($def),
        ]));

        if ($sections === []) {
            return null;
        }

        $today = now()->toDateString();
        $existing = Briefing::where('edition', $edition)->where('edition_date', $today)->first();

        if ($existing !== null && $existing->sections == $sections) {
            return $existing;
        }

        return Briefing::updateOrCreate(['edition' => $edition, 'edition_date' => $today], [
            'title' => $def['name'], 'sections' => $sections, 'written_by' => 'code', 'published_at' => now(),
        ]);
    }

    /** @return array<string, mixed>|null */
    private function releases(array $def): ?array
    {
        $items = Item::listed()->with('source:id,name')->where('published_at', '>=', now()->subDays(7))
            ->when($def['industries'], fn ($q) => $q->whereHas('industries', fn ($i) => $i->whereIn('slug', $def['industries'])))
            ->when($def['region'], fn ($q, $r) => $q->whereHas('geographies', fn ($g) => $g->where('region', 'like', '%'.$r.'%')))
            ->orderByDesc('published_at')->limit(6)->get();

        if ($items->isEmpty()) {
            return null;
        }

        return ['heading' => 'Published this week', 'kind' => 'releases', 'entries' => $items->map(fn (Item $i) => [
            'text' => $i->title, 'href' => route('intel.item', $i), 'source' => $i->source->name, 'when' => $i->published_at->format('j M Y'),
        ])->all()];
    }

    /** @return array<string, mixed>|null */
    private function figures(array $def): ?array
    {
        $entries = [];

        foreach ($def['figures'] as [$key, $code]) {
            $indicator = $this->reader->indicatorByKey($key);
            $place = $this->reader->geographyByCode($code);
            $figure = $indicator && $place ? $this->reader->figure($indicator, $place, 24) : null;

            if ($figure !== null) {
                $entries[] = $this->line($figure);
            }
        }

        return $entries === [] ? null : ['heading' => 'Where the figures stand', 'kind' => 'figures', 'entries' => $entries];
    }

    /** @return array<string, mixed>|null */
    private function movements(array $def): ?array
    {
        $signals = Signal::with(['indicator.source', 'geography'])->where('detected_at', '>=', now()->subDays(30))
            ->whereHas('indicator', fn ($q) => $q->where('is_published', true)
                ->when($def['industries'], fn ($i) => $i->whereHas('industries', fn ($x) => $x->whereIn('slug', $def['industries']))))
            ->orderByDesc('detected_at')->limit(4)->get();

        if ($signals->isEmpty()) {
            return null;
        }

        return ['heading' => 'What moved unusually', 'kind' => 'movements', 'entries' => $signals->map(fn (Signal $s) => [
            'text' => $this->movement($s), 'href' => route('intel.indicator', [$s->indicator, 'place' => $s->geography->code]), 'source' => $s->indicator->source->name, 'when' => $s->period->format('F Y'),
        ])->all()];
    }

    /** @return array{text: string, href: string, source: string, when: string} */
    private function line(FigureData $f): array
    {
        $change = $f->change();

        return [
            'text' => "{$f->indicator->name}, {$f->geography->name}: {$f->value()} {$f->note()}".($change ? '. '.$change['words'].'.' : '.'),
            'href' => route('intel.indicator', [$f->indicator, 'place' => $f->geography->code]), 'source' => $f->source->name, 'when' => (string) $f->period(),
        ];
    }

    public function movement(Signal $s): string
    {
        $i = $s->indicator;
        $value = Reading\Format::figure($s->value, $i->unit, $i->decimals);
        $before = $s->compared_to === null ? '' : ', from '.Reading\Format::figure($s->compared_to, $i->unit, $i->decimals);

        return "{$i->name} in {$s->geography->name} was {$value}{$before}: a ".($s->kind === 'rise' ? 'larger rise' : 'larger fall').' than usual for this measure.';
    }
}
