<?php

namespace App\Models\Intelligence;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A measured thing, with its definition, unit and source.
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string $unit
 * @property string $frequency
 * @property int $decimals
 * @property int $stale_after_days
 */
class Indicator extends IntelligenceModel
{
    protected function casts(): array
    {
        return ['settings' => 'array', 'is_published' => 'boolean', 'licence_checked_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'key';
    }

    /** @return BelongsTo<Source, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    /** @return BelongsToMany<Industry, $this> */
    public function industries(): BelongsToMany
    {
        return $this->belongsToMany(Industry::class);
    }

    /** @return HasMany<Observation, $this> */
    public function observations(): HasMany
    {
        return $this->hasMany(Observation::class);
    }
}
