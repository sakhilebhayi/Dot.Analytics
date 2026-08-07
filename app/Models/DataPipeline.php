<?php

namespace App\Models;

use App\Models\Concerns\HasTeamScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $data_connector_id
 * @property string $name
 * @property string $pipeline_type
 * @property array $source_config
 * @property array $transform_config
 * @property array $destination_config
 * @property string $status
 * @property string|null $schedule
 * @property bool $is_incremental
 */
class DataPipeline extends Model
{
    use HasTeamScope;

    protected $fillable = [
        'team_id', 'data_connector_id', 'name', 'description', 'pipeline_type',
        'source_config', 'transform_config', 'destination_config',
        'schedule', 'status', 'is_incremental', 'watermark_column',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_incremental' => true,
        'pipeline_type' => 'elt',
        'status' => 'draft',
    ];

    protected $casts = [
        'source_config' => 'array',
        'transform_config' => 'array',
        'destination_config' => 'array',
        'is_incremental' => 'boolean',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function connector(): BelongsTo
    {
        return $this->belongsTo(DataConnector::class, 'data_connector_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(PipelineRun::class);
    }

    public function lastRun(): ?PipelineRun
    {
        return $this->runs()->latest('id')->first();
    }
}
