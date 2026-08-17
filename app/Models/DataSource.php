<?php

namespace App\Models;

use App\Models\Concerns\HasTeamScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataSource extends Model
{
    use HasFactory, HasTeamScope;

    protected $fillable = [
        'team_id', 'platform', 'display_name', 'base_url', 'status',
        'last_synced_at', 'connected_at', 'config', 'capabilities',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
        'connected_at' => 'datetime',
        'config' => 'array',
        'capabilities' => 'array',
    ];

    /**
     * config currently only ever holds webhook_secret (see
     * ConnectPlatformAction::handle() / IngestController::receive()) --
     * hidden so it never leaks through catalog()/connected()/show()'s
     * default JSON serialization. PlatformController::connect() re-attaches
     * it explicitly to that one response only, the single moment it's
     * meant to be surfaced to the connecting caller.
     */
    protected $hidden = ['config'];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(AnalyticsSnapshot::class);
    }

    public function isConnected(): bool
    {
        return $this->status === 'connected';
    }
}
