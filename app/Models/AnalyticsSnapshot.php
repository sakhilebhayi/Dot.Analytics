<?php

namespace App\Models;

use App\Models\Concerns\HasTeamScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsSnapshot extends Model
{
    use HasTeamScope;

    protected $fillable = [
        'team_id', 'data_source_id', 'snapshot_type', 'payload', 'captured_at', 'taken_at', 'period',
    ];

    protected $casts = [
        'payload' => 'array',
        'captured_at' => 'datetime',
        'taken_at' => 'datetime',
    ];

    /** Whether this snapshot follows the metrics contract (older, free-form ones have no taken_at). */
    public function isContractSnapshot(): bool
    {
        return $this->taken_at !== null && $this->period !== null;
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function dataSource(): BelongsTo
    {
        return $this->belongsTo(DataSource::class);
    }
}
