<?php

namespace App\Models;

use App\Models\Concerns\HasTeamScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DataSource extends Model
{
    use HasFactory, HasTeamScope;

    protected $fillable = [
        'team_id', 'platform', 'display_name', 'base_url', 'status',
        'last_synced_at', 'connected_at', 'config', 'capabilities', 'webhook_secret',
    ];

    /** The HMAC secret is write-only: it is shown once when issued and never serialised. */
    protected $hidden = ['webhook_secret'];

    protected $casts = [
        'webhook_secret' => 'encrypted',
        'last_synced_at' => 'datetime',
        'connected_at' => 'datetime',
        'config' => 'array',
        'capabilities' => 'array',
    ];

    protected static function booted(): void
    {
        // Older callers put the secret inside the plain-JSON config. Never let it
        // be persisted there: move it into the encrypted column on every save.
        static::saving(function (DataSource $source): void {
            $config = $source->config;
            if (is_array($config) && array_key_exists('webhook_secret', $config)) {
                $legacy = $config['webhook_secret'];
                unset($config['webhook_secret']);
                $source->config = $config;
                if (is_string($legacy) && $legacy !== '') {
                    $source->webhook_secret = $legacy;
                }
            }
        });
    }

    /**
     * Issue a fresh webhook secret and return it. This is the only time the
     * plaintext is available to the caller.
     */
    public function rotateWebhookSecret(): string
    {
        $secret = Str::random(48);
        $this->webhook_secret = $secret;
        $this->save();

        return $secret;
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(AnalyticsSnapshot::class);
    }

    public function isConnected(): bool
    {
        return $this->status === 'connected';
    }
}
