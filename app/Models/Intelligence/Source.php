<?php

namespace App\Models\Intelligence;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Somewhere facts come from, with what its licence lets us do, and whether it is working.
 *
 * @property string $key
 * @property string $name
 * @property string $status
 * @property int $consecutive_failures
 * @property int $collect_every_minutes
 * @property int $stale_after_hours
 * @property array<string, mixed>|null $settings
 * @property array<string, mixed>|null $cursor
 * @property Carbon|null $last_success_at
 */
class Source extends IntelligenceModel
{
    public $timestamps = true;

    protected $table = 'sources';

    protected function casts(): array
    {
        return [
            'settings' => 'array', 'cursor' => 'array', 'allows_summary' => 'boolean', 'allows_commercial' => 'boolean', 'is_enabled' => 'boolean',
            'next_due_at' => 'datetime', 'last_attempt_at' => 'datetime', 'last_success_at' => 'datetime', 'last_failure_at' => 'datetime',
        ];
    }

    /** @return HasMany<SourceRun, $this> */
    public function runs(): HasMany
    {
        return $this->hasMany(SourceRun::class);
    }

    /** @return HasMany<Indicator, $this> */
    public function indicators(): HasMany
    {
        return $this->hasMany(Indicator::class);
    }

    /** No good collection inside the window this source promises. */
    public function isStale(): bool
    {
        return $this->last_success_at === null || $this->last_success_at->lt(now()->subHours($this->stale_after_hours));
    }
}
