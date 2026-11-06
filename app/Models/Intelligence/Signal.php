<?php

namespace App\Models\Intelligence;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** A movement worth a person's attention, worked out from the numbers. Never written by a model. */
class Signal extends IntelligenceModel
{
    protected function casts(): array
    {
        return ['detected_at' => 'datetime', 'value' => 'float', 'compared_to' => 'float', 'size' => 'float'];
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
