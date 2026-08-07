<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetricDefinition extends Model
{
    use HasFactory;

    protected $fillable = [
        'key', 'label', 'source_platform', 'engine', 'aggregation', 'unit', 'description',
    ];

    public function computedMetrics(): HasMany
    {
        return $this->hasMany(ComputedMetric::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(AnalyticsAlert::class);
    }
}
