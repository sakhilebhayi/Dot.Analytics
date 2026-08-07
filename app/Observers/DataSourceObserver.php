<?php

namespace App\Observers;

use App\Events\Analytics\PlatformConnected;
use App\Events\Analytics\PlatformDisconnected;
use App\Models\AuditLog;
use App\Models\DataSource;
use Illuminate\Support\Facades\Auth;

/**
 * Observes DataSource model lifecycle to:
 * 1. Fire domain events when platforms connect or disconnect.
 * 2. Write immutable audit log entries for every change.
 */
class DataSourceObserver
{
    public function created(DataSource $dataSource): void
    {
        $this->audit($dataSource, 'platform.created', null, $dataSource->toArray());

        if ($dataSource->status === 'connected') {
            PlatformConnected::dispatch($dataSource, $dataSource->team_id);
        }
    }

    public function updated(DataSource $dataSource): void
    {
        $dirty = $dataSource->getDirty();
        $this->audit($dataSource, 'platform.updated', $dataSource->getOriginal(), $dirty);

        // Status transitioned to connected
        if (isset($dirty['status']) && $dirty['status'] === 'connected') {
            PlatformConnected::dispatch($dataSource, $dataSource->team_id);
        }

        // Status transitioned away from connected (disconnect)
        if (isset($dirty['status']) && $dataSource->getOriginal('status') === 'connected') {
            PlatformDisconnected::dispatch(
                $dataSource->team_id,
                $dataSource->platform,
                $dataSource->display_name,
            );
        }
    }

    public function deleted(DataSource $dataSource): void
    {
        $this->audit($dataSource, 'platform.deleted', $dataSource->toArray(), null);

        if ($dataSource->status === 'connected') {
            PlatformDisconnected::dispatch(
                $dataSource->team_id,
                $dataSource->platform,
                $dataSource->display_name,
            );
        }
    }

    private function audit(DataSource $dataSource, string $event, ?array $old, ?array $new): void
    {
        AuditLog::create([
            'team_id' => $dataSource->team_id,
            'user_id' => Auth::id(),
            'actor_type' => Auth::check() ? 'user' : 'system',
            'actor_id' => (string) Auth::id(),
            'event' => $event,
            'auditable_type' => DataSource::class,
            'auditable_id' => $dataSource->id,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
