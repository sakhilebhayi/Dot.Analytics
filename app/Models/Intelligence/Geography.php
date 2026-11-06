<?php

namespace App\Models\Intelligence;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $slug
 * @property string $kind
 */
class Geography extends IntelligenceModel
{
    protected $table = 'geographies';

    protected function casts(): array
    {
        return ['is_featured' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return HasMany<Observation, $this> */
    public function observations(): HasMany
    {
        return $this->hasMany(Observation::class);
    }
}
