<?php

namespace App\Models;

use App\Models\Concerns\HasTeamScope;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One metric's value for one day (period 'daily', period_date) and how it moved
 * from the previous point. Written only by App\Services\Metrics\ComputeMetrics
 * from real, contract-validated snapshots. A metric with no snapshot has no row.
 */
class ComputedMetric extends Model
{
    use HasTeamScope;

    protected $fillable = [
        'team_id', 'metric_definition_id', 'value', 'period', 'period_date',
        'previous_value', 'delta', 'delta_pct', 'previous_period_date',
        'currency', 'observed_at', 'snapshot_id', 'is_demo',
    ];

    protected $casts = [
        'value' => 'decimal:4',
        'previous_value' => 'decimal:4',
        'delta' => 'decimal:4',
        'delta_pct' => 'decimal:4',
        'period_date' => 'date',
        'previous_period_date' => 'date',
        'observed_at' => 'datetime',
        'is_demo' => 'boolean',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function metricDefinition(): BelongsTo
    {
        return $this->belongsTo(MetricDefinition::class, 'metric_definition_id');
    }

    public function definition(): BelongsTo
    {
        return $this->metricDefinition();
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(AnalyticsSnapshot::class, 'snapshot_id');
    }

    /**
     * Stale means the platform last observed this value more than
     * metrics.stale_after_periods daily periods ago. Judged at read time so a
     * value ages into stale on its own, without waiting for a recompute.
     */
    public function isStale(?CarbonInterface $now = null): bool
    {
        if ($this->observed_at === null) {
            return true;
        }

        $limit = ($now ?? Carbon::now())->copy()->subDays((int) config('metrics.stale_after_periods', 2));

        return $this->observed_at->lessThan($limit);
    }
}
