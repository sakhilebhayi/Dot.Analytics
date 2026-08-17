<?php

namespace App\Models;

use App\Models\Concerns\HasTeamScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Scout\Searchable;

class AnalyticsAlert extends Model
{
    use HasFactory, HasTeamScope, Searchable;

    protected $table = 'analytics_alerts';

    protected $fillable = [
        'team_id', 'metric_definition_id', 'title', 'description',
        'severity', 'status', 'context', 'triggered_at', 'resolved_at',
    ];

    protected $casts = [
        'context' => 'array',
        'triggered_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function toSearchableArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function metricDefinition(): BelongsTo
    {
        return $this->belongsTo(MetricDefinition::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
