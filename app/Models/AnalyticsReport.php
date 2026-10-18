<?php

namespace App\Models;

use App\Models\Concerns\HasTeamScope;
use App\Models\Concerns\SearchesWithinTeam;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AnalyticsReport extends Model
{
    use HasFactory, HasTeamScope, SearchesWithinTeam;

    /** The kinds of data a report can contain. */
    public const REPORT_TYPES = ['insights', 'alerts', 'recommendations', 'metrics'];

    protected $table = 'analytics_reports';

    protected $fillable = [
        'team_id', 'user_id', 'title', 'description', 'type', 'cron_expression', 'config',
    ];

    protected $casts = [
        'config' => 'array',
    ];

    /**
     * What this report contains. The panel stores it in config.report_type; the
     * API stores it in `type` (insights, alerts ...). Null when neither says.
     */
    public function reportType(): ?string
    {
        foreach ([$this->config['report_type'] ?? null, $this->type] as $candidate) {
            if (is_string($candidate) && in_array($candidate, self::REPORT_TYPES, true)) {
                return $candidate;
            }
        }

        return is_string($this->config['report_type'] ?? null) ? null : 'insights';
    }

    public function toSearchableArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
        ];
    }

    /** @return BelongsTo<Team, $this> */
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
