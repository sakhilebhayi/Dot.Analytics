<?php

namespace App\Services\Metrics;

use App\Models\AnalyticsSnapshot;
use App\Models\DataSource;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/**
 * Validates a posted snapshot against the contract and stores it, once.
 *
 * Idempotency: (data source, platform, taken_at, period) identifies a snapshot.
 * A second post of the same identity returns the ORIGINAL row untouched, even if
 * its numbers differ: a publisher that wants to restate a day must not be able
 * to do it by replaying.
 *
 * The same service backs the HTTP endpoint and the local Demo seeder, so seeded
 * data cannot be shaped in a way a real publisher could not send.
 */
class SnapshotIngestService
{
    public const TYPE_WEBHOOK = 'webhook';

    public const TYPE_DEMO = 'demo';

    public function __construct(private readonly SnapshotContract $contract = new SnapshotContract) {}

    /**
     * @param  array<string, mixed>  $body  the decoded request body
     * @return array{snapshot: AnalyticsSnapshot, replayed: bool, accepted: list<string>, dropped: list<string>}
     *
     * @throws ValidationException
     */
    public function ingest(DataSource $source, array $body, string $snapshotType = self::TYPE_WEBHOOK): array
    {
        $valid = $this->contract->validate($source->platform, $body);

        $existing = $this->find($source, $valid['period'], $valid['taken_at']);
        if ($existing) {
            return $this->result($existing, true, []);
        }

        $payload = [
            'platform' => $valid['platform'],
            'taken_at' => $valid['taken_at']->toIso8601String(),
            'period' => $valid['period'],
            'metrics' => $valid['metrics'],
        ];
        if ($valid['currency'] !== null) {
            $payload['currency'] = $valid['currency'];
        }

        try {
            $snapshot = AnalyticsSnapshot::withoutGlobalScopes()->create([
                'team_id' => $source->team_id,
                'data_source_id' => $source->id,
                'snapshot_type' => $snapshotType,
                'payload' => $payload,
                'captured_at' => now(),
                'taken_at' => $valid['taken_at'],
                'period' => $valid['period'],
            ]);
        } catch (UniqueConstraintViolationException) {
            // A concurrent replay won the race; hand back its row.
            return $this->result($this->find($source, $valid['period'], $valid['taken_at']), true, []);
        }

        $source->forceFill(['last_synced_at' => now()])->save();

        return $this->result($snapshot, false, $valid['dropped']);
    }

    private function find(DataSource $source, string $period, \DateTimeInterface $takenAt): ?AnalyticsSnapshot
    {
        return AnalyticsSnapshot::withoutGlobalScopes()
            ->where('data_source_id', $source->id)
            ->where('period', $period)
            ->where('taken_at', $takenAt)
            ->first();
    }

    /**
     * @param  list<string>  $dropped  only known for a snapshot stored by this call
     * @return array{snapshot: AnalyticsSnapshot, replayed: bool, accepted: list<string>, dropped: list<string>}
     */
    private function result(AnalyticsSnapshot $snapshot, bool $replayed, array $dropped): array
    {
        return [
            'snapshot' => $snapshot,
            'replayed' => $replayed,
            'accepted' => array_keys($snapshot->payload['metrics'] ?? []),
            'dropped' => $dropped,
        ];
    }
}
