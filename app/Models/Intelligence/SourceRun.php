<?php

namespace App\Models\Intelligence;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One attempt at a source, kept so any fact can be traced to the collection that brought it in. */
class SourceRun extends IntelligenceModel
{
    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    /** @return BelongsTo<Source, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }
}
