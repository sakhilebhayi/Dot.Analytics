<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnalyticsReport extends Model
{
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

    public function latestRun(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ReportRun::class, 'analytics_report_id')->latestOfMany();
    }
}
