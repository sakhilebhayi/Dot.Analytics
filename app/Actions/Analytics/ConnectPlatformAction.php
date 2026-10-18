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
        return $this->handleWithSecret($team, $platformKey, $baseUrl)[0];
    }

    /**
     * Connect the platform and, if it has no webhook secret yet, issue one.
     * The plaintext secret is returned only here, once; it is stored encrypted
     * and never serialised afterwards. Ingest requires a signed request, so a
     * source without a secret cannot receive data.
     *
     * @return array{0: DataSource, 1: string|null} [source, newly issued plaintext secret or null]
     *
     * @throws ValidationException
     */
    public function handleWithSecret(Team $team, string $platformKey, ?string $baseUrl = null): array
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
                'base_url' => $baseUrl,
                'status' => 'connected',
                'capabilities' => $catalog['contributions'],
                'connected_at' => now(),
            ],
        );

        // The observer fires PlatformConnected which dispatches engine jobs.
        // No need to manually dispatch here — decoupled by design.

        $issued = $dataSource->webhook_secret ? null : $dataSource->rotateWebhookSecret();

        return [$dataSource, $issued];
    }
}
