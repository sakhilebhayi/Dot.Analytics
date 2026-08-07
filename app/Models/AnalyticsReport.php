<?php

namespace App\Models;

use App\Models\Concerns\HasTeamScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AnalyticsReport extends Model
{
    use HasTeamScope;

    protected $table = 'analytics_reports';

    protected $fillable = [
        'team_id', 'user_id', 'title', 'description', 'type', 'cron_expression', 'config',
    ];

    protected $casts = [
        'config' => 'array',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(ReportRun::class);
    }

    public function latestRun(): HasOne
    {
        return $this->hasOne(ReportRun::class, 'analytics_report_id')->latestOfMany();
    }
}
