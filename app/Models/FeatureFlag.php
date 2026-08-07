<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string|null $description
 * @property bool $enabled_globally
 * @property array|null $enabled_for_teams
 * @property array|null $enabled_for_users
 * @property float $rollout_percentage
 * @property string $environment
 */
class FeatureFlag extends Model
{
    protected $fillable = [
        'key', 'name', 'description', 'enabled_globally',
        'enabled_for_teams', 'enabled_for_users', 'rollout_percentage', 'environment',
    ];

    protected $casts = [
        'enabled_globally' => 'boolean',
        'enabled_for_teams' => 'array',
        'enabled_for_users' => 'array',
        'rollout_percentage' => 'float',
    ];
}
