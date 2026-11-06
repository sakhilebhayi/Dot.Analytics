<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportRun extends Model
{
    protected $fillable = [
        'analytics_report_id', 'status', 'output', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'output' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /** How many runs of one report are kept. Older ones go as new ones arrive, so a report's history cannot grow without end. */
    public const KEEP = 30;

    protected static function booted(): void
    {
        static::created(function (self $run) {
            $cut = static::where('analytics_report_id', $run->analytics_report_id)->orderByDesc('id')->skip(self::KEEP)->value('id');

            if ($cut !== null) {
                static::where('analytics_report_id', $run->analytics_report_id)->where('id', '<=', $cut)->delete();
            }
        });
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(AnalyticsReport::class, 'analytics_report_id');
    }
}
