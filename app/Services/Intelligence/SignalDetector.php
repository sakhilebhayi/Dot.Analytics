<?php

namespace App\Services\Intelligence;

use App\Models\Intelligence\Indicator;
use App\Models\Intelligence\Observation;
use App\Models\Intelligence\Signal;

/**
 * Finds movements worth a look, from the numbers alone: the latest value against the same measure's own history. A
 * movement is flagged when it is large compared with how much that measure normally changes. Needs enough history to
 * know what normal is, and says nothing otherwise.
 */
class SignalDetector
{
    public const MIN_POINTS = 9;

    public const THRESHOLD = 2.0;

    public function detect(): int
    {
        $found = 0;

        Indicator::where('is_published', true)->each(function (Indicator $indicator) use (&$found) {
            $geographies = Observation::where('indicator_id', $indicator->id)->distinct()->pluck('geography_id');

            foreach ($geographies as $geography) {
                $found += $this->one($indicator, (int) $geography) ? 1 : 0;
            }
        });

        return $found;
    }

    private function one(Indicator $indicator, int $geography): bool
    {
        $rows = Observation::where('indicator_id', $indicator->id)->where('geography_id', $geography)
            ->orderByDesc('period')->limit(60)->get(['period', 'value'])->reverse()->values();

        if ($rows->count() < self::MIN_POINTS) {
            return false;
        }

        $values = $rows->pluck('value')->all();
        $changes = [];

        for ($i = 1; $i < count($values); $i++) {
            $changes[] = $values[$i] - $values[$i - 1];
        }

        $latest = array_pop($changes);
        $mean = array_sum($changes) / max(1, count($changes));
        $sd = sqrt(array_sum(array_map(fn ($c) => ($c - $mean) ** 2, $changes)) / max(1, count($changes)));

        if ($sd < 1e-9) {
            return false;
        }

        $z = ($latest - $mean) / $sd;

        if (abs($z) < self::THRESHOLD) {
            return false;
        }

        $last = $rows->last();

        Signal::updateOrCreate(
            ['indicator_id' => $indicator->id, 'geography_id' => $geography, 'period' => $last->period->toDateString(), 'kind' => $z > 0 ? 'rise' : 'fall'],
            ['value' => $last->value, 'compared_to' => $values[count($values) - 2], 'size' => round($z, 2), 'detected_at' => now()],
        );

        return true;
    }
}
