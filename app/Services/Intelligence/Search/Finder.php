<?php

namespace App\Services\Intelligence\Search;

use App\Models\Intelligence\Geography;
use App\Models\Intelligence\Indicator;
use App\Models\Intelligence\Industry;
use App\Models\Intelligence\Item;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Finds what the library holds, with the reason for every match. Plain matching over our own tables, so a result can be
 * explained and a missing one can be reported honestly. A headline counts for more than a summary, newer for more than older.
 */
class Finder
{
    private const LIMIT = 10;

    /** @return array{indicators: Collection, items: Collection, industries: Collection, places: Collection} */
    public function find(Question $q): array
    {
        $tokens = array_slice($q->tokens, 0, 6);

        return [
            'indicators' => $tokens === [] ? collect() : $this->indicators($tokens),
            'items' => $tokens === [] ? collect() : $this->items($tokens),
            'industries' => $tokens === [] || $q->industry ? collect() : $this->ranked(Industry::where(fn ($w) => $this->anyToken($w, $tokens, ['name', 'summary']))->get(), $tokens, fn (Industry $i) => [[$i->name, 3], [$i->summary, 1]]),
            'places' => $tokens === [] || $q->place ? collect() : $this->ranked(Geography::where('kind', '!=', 'aggregate')->where(fn ($w) => $this->anyToken($w, $tokens, ['name']))->get(), $tokens, fn (Geography $g) => [[$g->name, 1]]),
        ];
    }

    /**
     * Keeps what matches a whole word beginning, not a fragment inside another word ("rate" is not in "Emirates"), best first.
     *
     * @param  Collection<int, Model>  $models
     * @param  list<string>  $tokens
     */
    private function ranked(Collection $models, array $tokens, \Closure $fields): Collection
    {
        return $models->map(fn ($m) => ['model' => $m, 'score' => $this->wordScore($tokens, $fields($m))])
            ->filter(fn ($r) => $r['score'] > 0)->sortByDesc('score')->take(5)->pluck('model')->values();
    }

    /** @param  list<string>  $tokens @param  list<array{0: string, 1: int}>  $fields */
    private function wordScore(array $tokens, array $fields): float
    {
        $score = 0.0;

        foreach ($fields as [$text, $weight]) {
            foreach ($tokens as $token) {
                $score += $weight * preg_match_all('/\b'.preg_quote($token, '/').'/iu', $text);
            }
        }

        return $score;
    }

    /** @param  list<string>  $tokens */
    private function indicators(array $tokens): Collection
    {
        return Indicator::where('is_published', true)->whereHas('observations')->with('source:id,name')
            ->where(fn ($w) => $this->anyToken($w, $tokens, ['name', 'definition', 'topic']))
            ->limit(80)->get()
            ->map(fn (Indicator $i) => ['indicator' => $i, 'score' => $this->wordScore($tokens, [[$i->name, 3], [(string) $i->definition, 1], [(string) $i->topic, 1]]), 'why' => $this->why($tokens, [[$i->name, 'its name'], [(string) $i->definition, 'its definition']])])
            ->filter(fn ($r) => $r['score'] > 0)->sortByDesc('score')->take(self::LIMIT)->values();
    }

    /** @param  list<string>  $tokens */
    private function items(array $tokens): Collection
    {
        return Item::listed()->with('source:id,name')->where('published_at', '>=', now()->subDays(365))
            ->where(fn ($w) => $this->anyToken($w, $tokens, ['title', 'source_summary']))
            ->orderByDesc('published_at')->limit(120)->get()
            ->map(fn (Item $i) => ['item' => $i, 'score' => $this->wordScore($tokens, [[$i->title, 3], [(string) $i->source_summary, 1]]), 'why' => $this->why($tokens, [[$i->title, 'the headline'], [(string) $i->source_summary, 'the summary']])])
            ->filter(fn ($r) => $r['score'] > 0)
            ->map(fn ($r) => ['score' => $r['score'] + max(0, 2 - $r['item']->published_at->diffInDays(now()) / 60)] + $r)
            ->sortByDesc('score')->take(self::LIMIT)->values();
    }

    /** @param  list<string>  $tokens @param  list<string>  $columns */
    private function anyToken($where, array $tokens, array $columns): void
    {
        foreach ($tokens as $token) {
            foreach ($columns as $column) {
                $where->orWhere($column, 'like', '%'.addcslashes($token, '%_\\').'%');
            }
        }
    }

    /** @param  list<string>  $tokens @param  list<array{0: string, 1: string}>  $fields */
    private function why(array $tokens, array $fields): string
    {
        $where = [];

        foreach ($fields as [$text, $label]) {
            foreach ($tokens as $token) {
                if (preg_match('/\b'.preg_quote($token, '/').'/iu', $text)) {
                    $where[$label] = true;
                }
            }
        }

        return $where === [] ? 'A related word' : 'Matched in '.implode(' and ', array_keys($where));
    }
}
