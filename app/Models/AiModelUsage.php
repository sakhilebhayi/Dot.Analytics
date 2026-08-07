<?php

namespace App\Models;

use App\Models\Concerns\HasTeamScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $team_id
 * @property string $provider
 * @property string $model
 * @property string $capability
 * @property string|null $engine
 * @property int $input_tokens
 * @property int $output_tokens
 * @property float $cost_usd
 * @property float|null $confidence
 * @property bool $fallback_used
 * @property int|null $latency_ms
 */
class AiModelUsage extends Model
{
    use HasFactory, HasTeamScope;

    protected $table = 'ai_model_usage';

    protected $fillable = [
        'team_id', 'provider', 'model', 'capability', 'engine',
        'input_tokens', 'output_tokens', 'cost_usd', 'confidence',
        'fallback_used', 'latency_ms',
    ];

    protected $casts = [
        'cost_usd' => 'float',
        'confidence' => 'float',
        'fallback_used' => 'boolean',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public static function totalCostForTeam(int $teamId): float
    {
        return (float) static::where('team_id', $teamId)->sum('cost_usd');
    }
}
