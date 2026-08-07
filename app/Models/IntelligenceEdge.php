<?php

namespace App\Models;

use App\Models\Concerns\HasTeamScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntelligenceEdge extends Model
{
    use HasTeamScope;

    protected $fillable = [
        'team_id', 'from_node_id', 'to_node_id', 'relationship', 'weight', 'metadata',
    ];

    protected $casts = [
        'weight' => 'float',
        'metadata' => 'array',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function fromNode(): BelongsTo
    {
        return $this->belongsTo(IntelligenceNode::class, 'from_node_id');
    }

    public function toNode(): BelongsTo
    {
        return $this->belongsTo(IntelligenceNode::class, 'to_node_id');
    }
}
