<?php

namespace App\Services\Intelligence\Search;

use App\Models\Intelligence\Geography;
use App\Models\Intelligence\Industry;
use Illuminate\Support\Str;

/** What a question asks about, read from its words. It only ever picks things that exist in the library. */
final class Question
{
    private const STOP = ['the', 'and', 'for', 'are', 'what', 'which', 'how', 'who', 'is', 'in', 'of', 'to', 'a', 'an', 'on', 'at', 'by', 'with', 'from', 'has', 'have', 'been', 'will', 'about', 'should', 'does', 'did', 'was', 'were', 'their', 'there', 'this', 'that', 'over', 'past', 'year', 'years', 'happening', 'business', 'businesses', 'monitor', 'biggest', 'facing', 'changed', 'developments'];

    /** Words that point at a measure we hold, to the keys of the series that measure it. */
    private const MEASURES = [
        'growth' => ['wb.gdp-growth'], 'gdp' => ['wb.gdp-growth'], 'economy' => ['wb.gdp-growth', 'wb.inflation', 'wb.unemployment'],
        'inflation' => ['wb.inflation'], 'prices' => ['wb.inflation'], 'unemployment' => ['wb.unemployment'], 'jobs' => ['wb.unemployment'],
        'interest' => ['bis.policy-rate-za', 'wb.lending-rate'], 'repo' => ['bis.policy-rate-za'], 'rates' => ['bis.policy-rate-za', 'wb.lending-rate'],
        'oil' => ['eia.brent', 'eia.wti'], 'brent' => ['eia.brent'], 'crude' => ['eia.brent', 'eia.wti'], 'fuel' => ['eia.brent'],
        'rand' => ['ecb.zar-eur'], 'currency' => ['ecb.zar-eur'], 'exchange' => ['ecb.zar-eur'],
        'investment' => ['wb.fdi', 'wb.investment'], 'electricity' => ['wb.electricity-access', 'wb.electricity-use'],
    ];

    /** @param  list<string>  $tokens @param  list<string>  $measureKeys */
    public function __construct(
        public readonly string $text,
        public readonly array $tokens,
        public readonly ?Geography $place,
        public readonly ?Industry $industry,
        public readonly array $measureKeys,
        public readonly bool $asksTrend,
    ) {}

    public static function parse(string $text): self
    {
        $lower = Str::lower(trim($text));
        $tokens = array_values(array_unique(array_filter(preg_split('/[^\p{L}\p{N}\-]+/u', $lower) ?: [], fn ($t) => mb_strlen($t) >= 3 && ! in_array($t, self::STOP, true))));

        $place = null;

        foreach (Geography::where('kind', 'country')->get() as $g) {
            if (preg_match('/\b'.preg_quote(Str::lower($g->name), '/').'\b/u', $lower) || ($g->code === 'ZAF' && preg_match('/\bsouth african?\b/', $lower))) {
                $place = $g;
                break;
            }
        }

        $keys = [];

        foreach (self::MEASURES as $word => $list) {
            if (preg_match('/\b'.$word.'/u', $lower)) {
                $keys = array_merge($keys, $list);
            }
        }

        // A question that names a measure ("inflation") is about that measure. Its word is also on an industry's list
        // ("finance"), and letting the list win would answer a different question than the one asked.
        $industry = null;

        foreach (Industry::orderBy('sort')->get() as $i) {
            $words = [Str::lower($i->name), Str::lower(explode(' and ', $i->name)[0])];

            if ($keys === []) {
                $words = array_merge($words, array_map('trim', (array) config('intelligence_topics.industries.'.$i->slug)));
            }

            foreach ($words as $w) {
                if (mb_strlen($w) >= 4 && preg_match('/\b'.preg_quote($w, '/').'/u', $lower)) {
                    $industry = $i;
                    break 2;
                }
            }
        }

        return new self($text, $tokens, $place, $industry, array_values(array_unique($keys)),
            (bool) preg_match('/\b(grow|growing|growth|declin|fall|falling|rise|rising|change|changed|trend)/', $lower));
    }

    public function isSpecific(): bool
    {
        return $this->place !== null || $this->industry !== null || $this->measureKeys !== [];
    }
}
