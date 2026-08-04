<?php

namespace App\Models;

use App\Models\Concerns\HasTeamScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int         $id
 * @property int         $team_id
 * @property string      $period
 * @property string      $period_date
 * @property string      $status
 * @property string|null $summary
 * @property array|null  $highlights
 * @property array|null  $risks
 * @property array|null  $recommendations
 * @property array|null  $kpis
 * @property array|null  $engines_consulted
 * @property int         $insight_count
 */
class ExecutiveBriefing extends Model
{
    use HasTeamScope;

    protected $fillable = [
        'team_id', 'period', 'period_date', 'status', 'summary',
        'highlights', 'risks', 'recommendations', 'kpis',
        'engines_consulted', 'insight_count',
    ];

    protected $casts = [
        'highlights'        => 'array',
        'risks'             => 'array',
        'recommendations'   => 'array',
        'kpis'              => 'array',
        'engines_consulted' => 'array',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }
}
