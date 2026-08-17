<?php

namespace App\Actions\Analytics;

use App\Events\Analytics\PlatformConnected;
use App\Models\DataSource;
use App\Models\Team;
use App\Services\IntelligenceEngineService;
use Illuminate\Support\Str;
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

        // IngestController::receive() only verifies a webhook's HMAC
        // signature when $source->config['webhook_secret'] is present --
        // but nothing anywhere in this codebase ever wrote one, so that
        // verification was silently dead for every connected platform.
        // Generate one here, once, and preserve it across a re-connect
        // (updateOrCreate's update path) rather than rotating it on every
        // call -- a platform that already configured the secret it was
        // given shouldn't have it invalidated by an idempotent re-connect.
        $existing = DataSource::where('team_id', $team->id)
            ->where('platform', $platformKey)
            ->first();
        $webhookSecret = $existing->config['webhook_secret'] ?? Str::random(40);

        $dataSource = DataSource::updateOrCreate(
            ['team_id' => $team->id, 'platform' => $platformKey],
            [
                'display_name' => $catalog['label'],
                'base_url' => $baseUrl,
                'status' => 'connected',
                'capabilities' => $catalog['contributions'],
                'connected_at' => now(),
                'config' => ['webhook_secret' => $webhookSecret],
            ],
        );

        // The observer fires PlatformConnected which dispatches engine jobs.
        // No need to manually dispatch here — decoupled by design.

        return $dataSource;
    }
}
