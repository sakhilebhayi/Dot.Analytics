<?php

namespace App\Models\Intelligence;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A company in a public register. Nothing here comes from a business's private data. */
class Company extends IntelligenceModel
{
    public $timestamps = true;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return BelongsTo<Geography, $this> */
    public function geography(): BelongsTo
    {
        return $this->belongsTo(Geography::class);
    }
}
