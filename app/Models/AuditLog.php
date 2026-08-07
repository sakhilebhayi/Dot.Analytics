<?php

namespace App\Models;

use App\Models\Concerns\HasTeamScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable audit log — no update or delete operations should ever touch this table.
 *
 * @property int $id
 * @property int $team_id
 * @property int|null $user_id
 * @property string $actor_type
 * @property string|null $actor_id
 * @property string $event
 * @property string|null $auditable_type
 * @property int|null $auditable_id
 * @property array|null $old_values
 * @property array|null $new_values
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $occurred_at
 */
class AuditLog extends Model
{
    use HasTeamScope;

    /**
     * Audit logs use occurred_at instead of Laravel's standard timestamps.
     * The occurred_at column has useCurrent() at the DB level.
     */
    public $timestamps = false;

    protected $fillable = [
        'team_id', 'user_id', 'actor_type', 'actor_id',
        'event', 'auditable_type', 'auditable_id',
        'old_values', 'new_values',
        'ip_address', 'user_agent', 'metadata', 'occurred_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata' => 'array',
        'occurred_at' => 'datetime',
    ];

    /** Prevent any updates to audit records. */
    protected static function boot(): void
    {
        parent::boot();

        static::updating(static function () {
            throw new \RuntimeException('Audit logs are immutable and cannot be updated.');
        });

        static::deleting(static function () {
            throw new \RuntimeException('Audit logs are immutable and cannot be deleted.');
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
