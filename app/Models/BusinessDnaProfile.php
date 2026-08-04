<?php

namespace App\Models;

use App\Models\Concerns\HasTeamScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessDnaProfile extends Model
{
    use HasTeamScope;

    protected $fillable = [
        'team_id', 'industry', 'operational_patterns', 'seasonal_trends',
        'risk_tolerance', 'growth_signals', 'decision_patterns', 'customer_behavior',
        'bottlenecks', 'industry_benchmarks', 'confidence_score', 'last_computed_at',
    ];

    protected $casts = [
        'operational_patterns' => 'array',
        'seasonal_trends'      => 'array',
        'risk_tolerance'       => 'array',
        'growth_signals'       => 'array',
        'decision_patterns'    => 'array',
        'customer_behavior'    => 'array',
        'bottlenecks'          => 'array',
        'industry_benchmarks'  => 'array',
        'confidence_score'     => 'float',
        'last_computed_at'     => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
