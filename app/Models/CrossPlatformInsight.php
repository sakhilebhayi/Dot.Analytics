<?php

namespace App\Models;

use App\Models\Concerns\HasTeamScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Scout\Searchable;

/**
 * @property int $id
 * @property int $team_id
 * @property string $title
 * @property string $narrative
 * @property array $platforms_involved
 * @property array|null $entities_involved
 * @property string $insight_type
 * @property float $confidence
 * @property string $severity
 * @property string $status
 * @property array|null $supporting_metrics
 */
class CrossPlatformInsight extends Model
{
    use HasFactory, HasTeamScope, Searchable;

    protected $fillable = [
        'team_id', 'title', 'narrative', 'platforms_involved',
        'entities_involved', 'insight_type', 'confidence', 'severity',
        'status', 'supporting_metrics',
    ];

    protected $casts = [
        'platforms_involved' => 'array',
        'entities_involved' => 'array',
        'supporting_metrics' => 'array',
        'confidence' => 'float',
    ];

    public function toSearchableArray(): array
    {
        return [
            'title' => $this->title,
            'narrative' => $this->narrative,
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function isNew(): bool
    {
        return $this->status === 'new';
    }

    public function review(): void
    {
        $this->update(['status' => 'reviewed']);
    }

    public function dismiss(): void
    {
        $this->update(['status' => 'dismissed']);
    }
}
