<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int         $id
 * @property int         $team_id
 * @property string      $name
 * @property string      $type
 * @property string      $driver
 * @property array       $config
 * @property string      $status
 * @property string      $version
 * @property int         $records_ingested
 * @property string|null $last_error
 * @property \Carbon\Carbon|null $last_tested_at
 * @property \Carbon\Carbon|null $last_ingested_at
 */
class DataConnector extends Model
{
    protected $fillable = [
        'team_id', 'name', 'type', 'driver', 'config', 'status', 'version',
        'records_ingested', 'last_tested_at', 'last_ingested_at', 'last_error',
    ];

    protected $casts = [
        'config'            => 'encrypted:array',
        'last_tested_at'    => 'datetime',
        'last_ingested_at'  => 'datetime',
        'records_ingested'  => 'integer',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function pipelines(): HasMany
    {
        return $this->hasMany(DataPipeline::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
