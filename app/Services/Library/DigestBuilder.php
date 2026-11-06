<?php

namespace App\Services\Library;

use App\Models\Intelligence\Geography;
use App\Models\Intelligence\Indicator;
use App\Models\Library\Alert;
use App\Models\User;
use App\Services\Intelligence\Reading\Reader;
use Illuminate\Support\Carbon;

/**
 * What goes in a person's email: how their watched figures stand, and what the library noticed since the last one. Nothing
 * is sent when there is nothing to say, because an empty email teaches people to ignore the next one.
 */
class DigestBuilder
{
    public function __construct(private readonly Reader $reader) {}

    /** @return array{figures: list<array<string, string>>, movements: list<Alert>, developments: list<Alert>, since: Carbon}|null */
    public function for(User $user): ?array
    {
        $since = $user->libraryPreference?->last_digest_at ?? now()->subDays(7);
        $alerts = Alert::where('user_id', $user->id)->where('created_at', '>', $since)->orderByDesc('id')->get();
        $movements = $alerts->where('kind', 'movement')->take(8)->values()->all();
        $developments = $alerts->where('kind', 'development')->take(12)->values()->all();

        if ($movements === [] && $developments === []) {
            return null;
        }

        return ['figures' => $this->figures($user), 'movements' => $movements, 'developments' => $developments, 'since' => $since];
    }

    /** @return list<array<string, string>> */
    private function figures(User $user): array
    {
        $out = [];

        foreach ($user->libraryWatches()->where('kind', 'indicator')->limit(8)->get() as $w) {
            $indicator = Indicator::where('key', $w->key)->first();
            $place = Geography::where('code', $w->place)->first();
            $figure = $indicator && $place ? $this->reader->figure($indicator, $place, 24) : null;

            if ($figure === null) {
                continue;
            }

            $out[] = ['name' => $indicator->name.', '.$place->name, 'value' => $figure->value().' '.$figure->note(), 'change' => $figure->change()['words'] ?? '', 'when' => (string) $figure->period(), 'source' => $figure->source->name, 'href' => route('intel.indicator', [$indicator, 'place' => $place->code])];
        }

        return $out;
    }
}
