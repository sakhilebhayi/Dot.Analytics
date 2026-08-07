<?php

namespace App\Jobs\Analytics;

use App\Models\AnalyticsSnapshot;
use App\Models\DataSource;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Ingests a snapshot of data from a connected platform.
 * Validates the payload, records data quality, and stores the snapshot.
 */
class IngestPlatformSnapshotJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        public readonly int $dataSourceId,
        public readonly array $payload,
        public readonly string $snapshotType = 'hourly',
    ) {}

    public function handle(): void
    {
        $source = DataSource::find($this->dataSourceId);
        if (! $source || ! $source->isConnected()) {
            return;
        }

        $quality = $this->assessQuality($this->payload);

        AnalyticsSnapshot::create([
            'team_id' => $source->team_id,
            'data_source_id' => $this->dataSourceId,
            'snapshot_type' => $this->snapshotType,
            'payload' => array_merge($this->payload, ['_quality' => $quality]),
            'captured_at' => now(),
        ]);

        $source->update(['last_synced_at' => now()]);
    }

    /**
     * Simple data quality assessment: completeness + null ratio.
     */
    private function assessQuality(array $payload): array
    {
        $total = count($payload, COUNT_RECURSIVE);
        $nulls = $this->countNulls($payload);
        $complete = $total > 0 ? round((1 - $nulls / max($total, 1)) * 100) : 0;

        return [
            'completeness_pct' => $complete,
            'total_fields' => $total,
            'null_fields' => $nulls,
            'assessed_at' => now()->toIso8601String(),
        ];
    }

    private function countNulls(array $data): int
    {
        $count = 0;
        array_walk_recursive($data, static function ($val) use (&$count) {
            if ($val === null || $val === '') {
                $count++;
            }
        });

        return $count;
    }
}
