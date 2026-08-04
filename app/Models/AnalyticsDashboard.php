<?php

namespace App\Models;

use App\Models\Concerns\HasTeamScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnalyticsDashboard extends Model
{
    use HasFactory, HasTeamScope;

    protected $table = 'analytics_dashboards';

    protected $fillable = [
        'team_id', 'user_id', 'title', 'is_default', 'layout',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'layout'     => 'array',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function widgets(): HasMany
    {
        return $this->hasMany(DashboardWidget::class)->orderBy('row')->orderBy('col');
    }
}
