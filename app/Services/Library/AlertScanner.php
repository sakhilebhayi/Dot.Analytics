<?php

namespace App\Services\Library;

use App\Models\Intelligence\Geography;
use App\Models\Intelligence\Indicator;
use App\Models\Intelligence\Industry;
use App\Models\Intelligence\Item;
use App\Models\Intelligence\Signal;
use App\Models\Library\Alert;
use App\Models\Library\Preference;
use App\Models\User;
use App\Services\Intelligence\BriefingBuilder;
use Illuminate\Support\Carbon;

/**
 * Looks, for one person, at what the library collected since it last looked, and records what touches what they watch.
 * An alert is written once (a person is never told the same thing twice) and says why they are being told.
 */
class AlertScanner
{
    private const PER_SCAN = 40;

    public function __construct(private readonly BriefingBuilder $briefings) {}

    /** @return int alerts written */
    public function scan(User $user): int
    {
        $pref = Preference::firstOrCreate(['user_id' => $user->id]);
        $since = $pref->last_scan_at ?? now()->subDays(7);
        $started = now();

        $watches = $user->libraryWatches()->get();

        if ($watches->isEmpty()) {
            $pref->update(['last_scan_at' => $started]);

            return 0;
        }

        $industries = Industry::whereIn('slug', $watches->where('kind', 'industry')->pluck('key'))->get();
        $countries = Geography::whereIn('code', $watches->where('kind', 'country')->pluck('key'))->get();
        $written = $this->movements($user, $watches, $industries, $countries, $since) + $this->developments($user, $industries, $countries, $since);

        $this->prune($user);
        $pref->update(['last_scan_at' => $started]);

        return $written;
    }

    private function movements($user, $watches, $industries, $countries, Carbon $since): int
    {
        $pairs = $watches->where('kind', 'indicator')->map(fn ($w) => [$w->key, $w->place])->values();
        $keys = Indicator::whereIn('key', $pairs->pluck(0))->pluck('id', 'key');
        $places = Geography::whereIn('code', $pairs->pluck(1))->pluck('id', 'code');
        $industryIndicators = $industries->isEmpty() ? collect() : Indicator::whereHas('industries', fn ($q) => $q->whereIn('industries.id', $industries->pluck('id')))->pluck('id');

        $signals = Signal::with(['indicator.source', 'geography'])->where('detected_at', '>', $since)
            ->whereHas('indicator', fn ($q) => $q->where('is_published', true))
            ->where(function ($w) use ($pairs, $keys, $places, $industryIndicators, $countries) {
                foreach ($pairs as [$key, $place]) {
                    if (isset($keys[$key], $places[$place])) {
                        $w->orWhere(fn ($x) => $x->where('indicator_id', $keys[$key])->where('geography_id', $places[$place]));
                    }
                }

                if ($industryIndicators->isNotEmpty()) {
                    $w->orWhereIn('indicator_id', $industryIndicators);
                }

                if ($countries->isNotEmpty()) {
                    $w->orWhereIn('geography_id', $countries->pluck('id'));
                }
            })->orderByDesc('detected_at')->limit(self::PER_SCAN)->get();

        $written = 0;

        foreach ($signals as $s) {
            $written += $this->write($user, 'movement', 'signal-'.$s->id, $this->briefings->movement($s), route('intel.indicator', [$s->indicator, 'place' => $s->geography->code]), $s->indicator->source->name.' · '.$s->period->format('F Y'));
        }

        return $written;
    }

    private function developments($user, $industries, $countries, Carbon $since): int
    {
        if ($industries->isEmpty() && $countries->isEmpty()) {
            return 0;
        }

        $items = Item::listed()->with('source:id,name')->where('collected_at', '>', $since)
            ->where(function ($w) use ($industries, $countries) {
                if ($industries->isNotEmpty()) {
                    $w->orWhereHas('industries', fn ($q) => $q->whereIn('industries.id', $industries->pluck('id')));
                }

                if ($countries->isNotEmpty()) {
                    $w->orWhereHas('geographies', fn ($q) => $q->whereIn('geographies.id', $countries->pluck('id')));
                }
            })->orderByDesc('published_at')->limit(self::PER_SCAN)->get();

        $written = 0;

        foreach ($items as $item) {
            $written += $this->write($user, 'development', $item->uuid, $item->title, route('intel.item', $item), $item->source->name.' · '.$item->published_at->format('j M Y'));
        }

        return $written;
    }

    private function write(User $user, string $kind, string $ref, string $title, string $href, string $detail): int
    {
        return Alert::query()->insertOrIgnore([
            'user_id' => $user->id, 'kind' => $kind, 'ref' => $ref, 'title' => mb_substr($title, 0, 500), 'detail' => mb_substr($detail, 0, 300), 'href' => mb_substr($href, 0, 300), 'created_at' => now(),
        ]);
    }

    /** Keeps the newest alerts the plan allows, and drops the rest. */
    private function prune(User $user): void
    {
        $keep = Entitlements::for($user)->alertsKept();
        $cut = Alert::where('user_id', $user->id)->orderByDesc('id')->skip($keep)->value('id');

        if ($cut !== null) {
            Alert::where('user_id', $user->id)->where('id', '<=', $cut)->delete();
        }
    }
}
