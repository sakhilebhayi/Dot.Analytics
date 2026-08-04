<?php

namespace App\Models;

use App\Models\Concerns\HasTeamScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IntelligenceNode extends Model
{
    use HasTeamScope;

    protected $fillable = [
        'team_id', 'entity_type', 'entity_id', 'label', 'source_platform', 'attributes',
    ];

    protected $casts = [
        'attributes' => 'array',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function outgoingEdges(): HasMany
    {
        return $this->hasMany(IntelligenceEdge::class, 'from_node_id');
    }

    public function incomingEdges(): HasMany
    {
        return $this->hasMany(IntelligenceEdge::class, 'to_node_id');
    }
}
