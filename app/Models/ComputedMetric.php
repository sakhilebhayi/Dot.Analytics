<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComputedMetric extends Model
{
    protected $fillable = [
        'team_id', 'metric_definition_id', 'value', 'period', 'period_date',
    ];

    protected $casts = [
        'value'       => 'decimal:4',
        'period_date' => 'date',
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
}
