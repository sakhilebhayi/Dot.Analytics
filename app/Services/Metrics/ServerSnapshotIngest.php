<?php

namespace App\Services\Metrics;

use App\Jobs\Analytics\ComputeMetricsJob;
use App\Models\AnalyticsSnapshot;
use App\Models\DataSource;
use App\Models\Team;
use App\Services\IntelligenceEngineService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/**
 * What happens to an AUTHENTICATED server-to-server snapshot: validate it against
 * the contract, find the team the company uuid maps to, make sure that team has a
 * data source for the platform, then store it through the very same service the
 * user-token endpoint uses. There is one pipeline (SnapshotIngestService ->
 * ComputeMetricsJob), not two.
 *
 * Authentication is NOT done here: the caller has already proved which platform it
 * is. This class trusts the platform and only maps the company.
 */
class ServerSnapshotIngest
{
    public const SOURCE_TYPE = 'push';

    public function __construct(
        private readonly SnapshotIngestService $ingest,
        private readonly SnapshotContract $contract,
        private readonly IntelligenceEngineService $engines,
    ) {}

    /**
     * @param  array<string, mixed>  $body  the decoded, authenticated request body
     * @return array{snapshot: AnalyticsSnapshot, replayed: bool, accepted: list<string>, dropped: list<string>}|null
     *                                                                                                                null when no team maps to the company
     *
     * @throws ValidationException
     */
    public function ingest(string $platform, array $body): ?array
    {
        // Validate first: a rejected snapshot must not provision anything.
        $this->contract->validate($platform, $body);

        $team = Team::where('infodot_team_uuid', strtolower((string) ($body['company_uuid'] ?? '')))->first();
        if (! $team) {
            return null;
        }

        $source = $this->sourceFor($team, $platform);
        $result = $this->ingest->ingest($source, $body, SnapshotIngestService::TYPE_PUSH);

        if (! $result['replayed']) {
            ComputeMetricsJob::dispatch($team->id);
        }

        return $result;
    }

    /**
     * The team's data source for the platform, created (connected, marked as a push
     * source) the first time a snapshot is accepted. Idempotent: a source the owner
     * already made is reused with its webhook secret untouched; a pending one is
     * connected. The owner never has to pre-create a secret for this path.
     */
    private function sourceFor(Team $team, string $platform): DataSource
    {
        $source = $this->find($team, $platform);

        if ($source === null) {
            try {
                return DataSource::create($this->attributes($team, $platform));
            } catch (UniqueConstraintViolationException) {
                // A concurrent first snapshot created it; use that row.
                $source = $this->find($team, $platform);
            }
        }

        if ($source->status !== 'connected') {
            $source->forceFill([
                'status' => 'connected',
                'connected_at' => $source->connected_at ?? now(),
                'config' => array_merge($source->config ?? [], ['source_type' => self::SOURCE_TYPE]),
            ])->save();
        }

        return $source;
    }

    private function find(Team $team, string $platform): ?DataSource
    {
        return DataSource::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('platform', $platform)
            ->first();
    }

    /** @return array<string, mixed> */
    private function attributes(Team $team, string $platform): array
    {
        $catalog = $this->engines->getPlatform($platform);

        return [
            'team_id' => $team->id,
            'platform' => $platform,
            'display_name' => $catalog['label'] ?? config("metrics.platforms.{$platform}.label", $platform),
            'status' => 'connected',
            'connected_at' => now(),
            'capabilities' => $catalog['contributions'] ?? [],
            'config' => ['source_type' => self::SOURCE_TYPE],
        ];
    }
}
