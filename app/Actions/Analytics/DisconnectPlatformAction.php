<?php

namespace App\Actions\Analytics;

use App\Models\DataSource;
use App\Models\Team;

/**
 * Disconnects a Dot platform from the intelligence layer.
 *
 * Deletes the DataSource record. The DataSourceObserver fires the
 * PlatformDisconnected domain event and writes the audit log entry
 * automatically — no manual event dispatch needed here.
 *
 * Returns the label of the disconnected platform, or null if it wasn't connected.
 */
class DisconnectPlatformAction
{
    public function handle(Team $team, string $platformKey): ?string
    {
        $source = DataSource::where('team_id', $team->id)
            ->where('platform', $platformKey)
            ->first();

        if (! $source) {
            return null;
        }

        $displayName = $source->display_name;
        $source->delete();

        return $displayName;
    }
}
