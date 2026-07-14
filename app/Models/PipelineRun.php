<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int         $id
 * @property int         $data_pipeline_id
 * @property string      $status
 * @property int         $records_read
 * @property int         $records_written
 * @property int         $records_failed
 * @property array|null  $data_quality_report
 * @property array|null  $lineage
 * @property string|null $error_message
 * @property int|null    $duration_ms
 * @property \Carbon\Carbon|null $started_at
 * @property \Carbon\Carbon|null $completed_at
 */
class PipelineRun extends Model
{
    protected $fillable = [
        'data_pipeline_id', 'status', 'trigger', 'records_read', 'records_written',
        'records_failed', 'data_quality_report', 'lineage', 'error_message',
        'duration_ms', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'data_quality_report' => 'array',
        'lineage'             => 'array',
        'started_at'          => 'datetime',
        'completed_at'        => 'datetime',
    ];

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(DataPipeline::class, 'data_pipeline_id');
    }

    public function qualityScore(): int
    {
        return (int) ($this->data_quality_report['completeness_pct'] ?? 0);
    }
}
