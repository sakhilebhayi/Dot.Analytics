<?php

namespace App\Models\Intelligence;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * A development: a headline with its date, its publisher and a link to the original.
 *
 * @property int $id
 * @property string $uuid
 * @property string $kind
 * @property string $title
 * @property string|null $source_summary
 * @property string $url
 * @property Carbon $published_at
 */
class Item extends IntelligenceModel
{
    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'collected_at' => 'datetime', 'ai_facts' => 'array', 'ai_generated_at' => 'datetime', 'hidden_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** What a visitor may see: not hidden, and not a copy of something already listed. */
    /** @param Builder<self> $query */
    public function scopeListed(Builder $query): void
    {
        $query->whereNull('hidden_at')->whereNull('duplicate_of');
    }

    /** @return BelongsTo<Source, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    /** @return BelongsToMany<Industry, $this> */
    public function industries(): BelongsToMany
    {
        return $this->belongsToMany(Industry::class)->withPivot('method');
    }

    /** @return BelongsToMany<Geography, $this> */
    public function geographies(): BelongsToMany
    {
        return $this->belongsToMany(Geography::class)->withPivot('method');
    }
}
