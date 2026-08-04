<?php

namespace App\Models;

use App\Models\Concerns\HasTeamScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int              $id
 * @property int              $team_id
 * @property string           $engine
 * @property string           $status
 * @property array|null       $platforms_consumed
 * @property int              $insights_generated
 * @property int              $metrics_computed
 * @property \Carbon\Carbon|null $started_at
 * @property \Carbon\Carbon|null $completed_at
 */
class IntelligenceEngineRun extends Model
{
    use HasTeamScope;

    protected $fillable = [
        'team_id', 'engine', 'status', 'platforms_consumed',
        'insights_generated', 'metrics_computed', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'platforms_consumed' => 'array',
        'started_at'         => 'datetime',
        'completed_at'       => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function durationSeconds(): ?int
    {
        if ($this->started_at && $this->completed_at) {
            return $this->started_at->diffInSeconds($this->completed_at);
        }
        return null;
    }
}
