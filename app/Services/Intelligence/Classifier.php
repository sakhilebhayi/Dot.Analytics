<?php

namespace App\Services\Intelligence;

use App\Models\Intelligence\Geography;
use App\Models\Intelligence\Industry;
use App\Models\Intelligence\Item;
use Illuminate\Support\Str;

/** Files items under industries and places by word lists anyone can read. The reason is recorded as the method. */
class Classifier
{
    /** @var array<string, int> */
    private array $industries = [];

    /** @var array<string, int> */
    private array $geographies = [];

    /** @var array<string, string> */
    private array $names = [];

    public function classifyPending(int $limit = 500): int
    {
        $done = 0;

        Item::whereNull('classified_at')->orderBy('id')->limit($limit)->get()->each(function (Item $item) use (&$done) {
            $this->classify($item);
            $done++;
        });

        return $done;
    }

    public function classify(Item $item): void
    {
        $this->load();
        $title = ' '.Str::lower($item->title).' ';
        $body = ' '.Str::lower((string) $item->source_summary).' ';

        $industries = [];

        foreach ((array) config('intelligence_topics.industries') as $slug => $words) {
            if (isset($this->industries[$slug]) && $this->hits($title, $body, $words) >= 1) {
                $industries[$this->industries[$slug]] = ['method' => 'rule'];
            }
        }

        $places = [];

        foreach ($this->names as $code => $name) {
            if ($this->hits($title, $body, [Str::lower($name)]) >= 1) {
                $places[$this->geographies[$code]] = ['method' => 'rule'];
            }
        }

        foreach ((array) config('intelligence_topics.places') as $code => $words) {
            if (isset($this->geographies[$code]) && $this->hits($title, $body, $words) >= 1) {
                $places[$this->geographies[$code]] = ['method' => 'rule'];
            }
        }

        $item->industries()->syncWithoutDetaching($industries);
        $item->geographies()->syncWithoutDetaching($places);
        $item->forceFill(['classified_at' => now()])->save();
    }

    /** A match in the headline counts double. */
    private function hits(string $title, string $body, array $words): int
    {
        $n = 0;

        foreach ($words as $word) {
            $n += 2 * substr_count($title, $word) + substr_count($body, $word);
        }

        return min($n, 99);
    }

    private function load(): void
    {
        if ($this->industries !== []) {
            return;
        }

        $this->industries = Industry::pluck('id', 'slug')->all();
        $this->geographies = Geography::where('kind', 'country')->pluck('id', 'code')->all();
        $this->geographies += Geography::where('kind', 'aggregate')->pluck('id', 'code')->all();
        $this->names = Geography::where('kind', 'country')->pluck('name', 'code')->all();
    }
}
