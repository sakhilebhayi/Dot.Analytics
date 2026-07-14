<?php

namespace App\Models;

use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Jetstream\Events\TeamCreated;
use Laravel\Jetstream\Events\TeamDeleted;
use Laravel\Jetstream\Events\TeamUpdated;
use Laravel\Jetstream\Team as JetstreamTeam;

/**
 * @property int    $id
 * @property string $name
 * @property bool   $personal_team
 * @property string $currency
 * @property string $locale
 * @property string $timezone
 * @property string|null $industry
 */
class Team extends JetstreamTeam
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'personal_team',
        'currency',
        'locale',
        'timezone',
        'industry',
    ];

    /**
     * The event map for the model.
     *
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [
        'created' => TeamCreated::class,
        'updated' => TeamUpdated::class,
        'deleted' => TeamDeleted::class,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'personal_team' => 'boolean',
        ];
    }

    public function dataSources(): HasMany
    {
        return $this->hasMany(DataSource::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(AnalyticsAlert::class);
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(Recommendation::class);
    }

    public function dashboards(): HasMany
    {
        return $this->hasMany(AnalyticsDashboard::class);
    }

    public function businessDna(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(BusinessDnaProfile::class);
    }

    public function crossPlatformInsights(): HasMany
    {
        return $this->hasMany(CrossPlatformInsight::class);
    }

    public function intelligenceEngineRuns(): HasMany
    {
        return $this->hasMany(IntelligenceEngineRun::class);
    }
}
