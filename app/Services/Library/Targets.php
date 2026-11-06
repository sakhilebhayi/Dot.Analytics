<?php

namespace App\Services\Library;

use App\Models\Intelligence\Geography;
use App\Models\Intelligence\Indicator;
use App\Models\Intelligence\Industry;
use App\Models\Intelligence\Item;
use App\Models\Library\Saved;
use App\Models\Library\Watch;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Turns what a person watches or saved into something printable, and refuses anything that does not exist in the library.
 * A watch on something that later goes away is shown as gone, never silently dropped and never an error.
 */
class Targets
{
    public const KINDS = ['industry', 'country', 'indicator'];

    /** @return array{kind: string, key: string, place: string, label: string, sub: string, href: string}|null */
    public function resolve(string $kind, string $key, string $place = ''): ?array
    {
        $key = trim($key);
        $place = strtoupper(trim($place));

        return match ($kind) {
            'industry' => $this->industry(Str::lower($key)),
            'country' => $this->country(strtoupper($key)),
            'indicator' => $this->indicator(Str::lower($key), $place),
            default => null,
        };
    }

    /** @return array{kind: string, key: string, place: string, label: string, sub: string, href: string}|null */
    public function forWatch(Watch $watch): ?array
    {
        return $this->resolve($watch->kind, $watch->key, $watch->place);
    }

    /** @return array{label: string, href: string}|null */
    public function forSaved(Saved $saved): ?array
    {
        if ($saved->kind === 'item') {
            $item = Item::listed()->where('uuid', $saved->ref)->first();

            return $item === null ? null : ['label' => $item->title, 'href' => route('intel.item', $item)];
        }

        return $saved->kind === 'search' ? ['label' => $saved->title, 'href' => route('intel.find', ['q' => $saved->ref])] : null;
    }

    /** @return array{kind: string, key: string, place: string, label: string, sub: string, href: string}|null */
    private function industry(string $key): ?array
    {
        $i = Industry::where('slug', $key)->first();

        return $i === null ? null : ['kind' => 'industry', 'key' => $i->slug, 'place' => '', 'label' => $i->name, 'sub' => 'Industry', 'href' => route('intel.industry', $i)];
    }

    /** @return array{kind: string, key: string, place: string, label: string, sub: string, href: string}|null */
    private function country(string $code): ?array
    {
        $g = Geography::where('code', $code)->where('kind', 'country')->first();

        return $g === null ? null : ['kind' => 'country', 'key' => $g->code, 'place' => '', 'label' => $g->name, 'sub' => 'Country', 'href' => route('intel.country', $g)];
    }

    /** @return array{kind: string, key: string, place: string, label: string, sub: string, href: string}|null */
    private function indicator(string $key, string $place): ?array
    {
        $i = Indicator::where('key', $key)->where('is_published', true)->first();
        $g = $place === '' ? null : Geography::where('code', $place)->first();

        if ($i === null || $g === null || ! $i->observations()->where('geography_id', $g->id)->exists()) {
            return null;
        }

        return ['kind' => 'indicator', 'key' => $i->key, 'place' => $g->code, 'label' => $i->name.', '.$g->name, 'sub' => 'Figure', 'href' => route('intel.indicator', [$i, 'place' => $g->code])];
    }

    /**
     * Everything a person watches, with what each is, and a flag for those that have gone.
     *
     * @return Collection<int, array{watch: Watch, target: array<string, string>|null}>
     */
    public function watching(User $user): Collection
    {
        return $user->libraryWatches()->orderBy('kind')->orderBy('id')->get()->map(fn (Watch $w) => ['watch' => $w, 'target' => $this->forWatch($w)]);
    }
}
