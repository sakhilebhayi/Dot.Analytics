<?php

namespace App\Models;

use App\Models\Concerns\HasTeamScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Scout\Searchable;

class Recommendation extends Model
{
    use HasFactory, HasTeamScope, Searchable;

    protected $fillable = [
        'team_id', 'engine', 'title', 'rationale', 'action_label',
        'action_url', 'priority', 'status', 'supporting_data',
    ];

    protected $casts = [
        'supporting_data' => 'array',
    ];

    public function toSearchableArray(): array
    {
        return [
            'title' => $this->title,
            'rationale' => $this->rationale,
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
