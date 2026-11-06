<?php

namespace App\Models\Intelligence;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One published value of an indicator, for one place and period.
 *
 * @property int $id
 * @property int $indicator_id
 * @property int $geography_id
 * @property Carbon $period
 * @property float $value
 */
class Observation extends IntelligenceModel
{
    protected function casts(): array
    {
        return ['value' => 'float', 'first_seen_at' => 'datetime', 'last_seen_at' => 'datetime'];
    }

    /** A period is a plain date: stored as text, so it compares exactly and never carries a time. */
    protected function period(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value === null ? null : Carbon::parse($value)->startOfDay(),
            set: fn ($value) => $value === null ? null : Carbon::parse($value)->toDateString(),
        );
    }

    /** @return BelongsTo<Indicator, $this> */
    public function indicator(): BelongsTo
    {
        return $this->belongsTo(Indicator::class);
    }

    /** @return BelongsTo<Geography, $this> */
    public function geography(): BelongsTo
    {
        return $this->belongsTo(Geography::class);
    }
}
