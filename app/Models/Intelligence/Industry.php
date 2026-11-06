<?php

namespace App\Models\Intelligence;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $summary
 */
class Industry extends IntelligenceModel
{
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return BelongsToMany<Indicator, $this> */
    public function indicators(): BelongsToMany
    {
        return $this->belongsToMany(Indicator::class);
    }

    /** @return BelongsToMany<Item, $this> */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Item::class);
    }
}
