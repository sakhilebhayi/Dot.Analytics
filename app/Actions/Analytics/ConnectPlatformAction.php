<?php

namespace App\Actions\Analytics;

use App\Events\Analytics\PlatformConnected;
use App\Models\DataSource;
use App\Models\Team;
use App\Services\IntelligenceEngineService;
use Illuminate\Validation\ValidationException;

/**
 * Connects a Dot platform to the intelligence layer.
 *
 * Validates the platform key against the catalog, creates or updates
 * the DataSource record, and fires the PlatformConnected domain event
 * which triggers downstream engine runs via the event listener.
 */
class ConnectPlatformAction
{
    public function __construct(
        private readonly IntelligenceEngineService $engineService,
    ) {}

    /**
     * @throws ValidationException
     */
    public function handle(Team $team, string $platformKey, ?string $baseUrl = null): DataSource
    {
        $catalog = $this->engineService->getPlatform($platformKey);

        if (! $catalog) {
            throw ValidationException::withMessages([
                'platform' => "Unknown platform key: {$platformKey}",
            ]);
        }

        $dataSource = DataSource::updateOrCreate(
            ['team_id' => $team->id, 'platform' => $platformKey],
            [
                'display_name' => $catalog['label'],
                'base_url'     => $baseUrl,
                'status'       => 'connected',
                'capabilities' => $catalog['contributions'],
                'connected_at' => now(),
            ],
        );

        // The observer fires PlatformConnected which dispatches engine jobs.
        // No need to manually dispatch here — decoupled by design.

        return $dataSource;
    }
}
