<?php

namespace Database\Seeders;

use App\Actions\Analytics\ConnectPlatformAction;
use App\Models\Team;
use App\Models\User;
use App\Services\Metrics\ComputeMetrics;
use App\Services\Metrics\SnapshotIngestService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * DEMO DATA, for local development only.
 *
 * Posts 30 days of invented-but-plausible snapshots for a few platforms through
 * the SAME SnapshotIngestService the HTTP endpoint uses, so the data cannot take a
 * shape a real publisher could not send. Every snapshot is stored with type
 * 'demo', which the computation carries through to the tiles, where it is shown
 * as DEMO DATA. It refuses to run outside local/testing and is deliberately not
 * called by DatabaseSeeder:
 *
 *     php artisan db:seed --class=DemoMetricsSeeder
 *
 * Set DEMO_ANALYTICS_TEAM_ID to seed an existing team instead of creating a
 * "Demo company (seeded demo data)" team.
 */
class DemoMetricsSeeder extends Seeder
{
    public const DAYS = 30;

    public const TEAM_NAME = 'Demo company (seeded demo data)';

    /**
     * platform => metric => [base, swing]; a 'currency' entry marks money.
     *
     * @var array<string, array<string, mixed>>
     */
    private const SHAPES = [
        'tasks' => ['open' => [120, 15], 'overdue' => [14, 4], 'completed_7d' => [88, 12], 'created_7d' => [95, 12]],
        'billing' => ['mrr_cents' => [48_500_000, 500_000], 'open_invoices' => [37, 5], 'overdue_invoices' => [6, 2], 'overdue_cents' => [1_250_000, 300_000], 'currency' => 'ZAR'],
        'hr' => ['headcount' => [64, 1], 'on_leave_today' => [5, 2], 'pending_leave_requests' => [3, 2]],
        'emall' => ['orders_7d' => [210, 30], 'revenue_7d_cents' => [8_400_000, 900_000], 'low_stock_products' => [9, 3], 'currency' => 'ZAR'],
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('DemoMetricsSeeder creates invented demo numbers and only runs in the local or testing environment.');
        }

        $team = $this->team();
        $ingest = app(SnapshotIngestService::class);

        // 06:00 UTC on each of the last DAYS days, minus any that are still in the future.
        $days = [];
        for ($daysAgo = self::DAYS - 1; $daysAgo >= 0; $daysAgo--) {
            $takenAt = now()->utc()->startOfDay()->addHours(6)->subDays($daysAgo);
            if (! $takenAt->isFuture()) {
                $days[] = $takenAt;
            }
        }

        foreach (self::SHAPES as $platform => $shape) {
            [$source] = app(ConnectPlatformAction::class)->handleWithSecret($team, $platform);

            foreach ($days as $index => $takenAt) {
                $ingest->ingest(
                    $source,
                    [
                        'company_uuid' => $team->infodot_team_uuid,
                        'platform' => $platform,
                        'taken_at' => $takenAt->toIso8601String(),
                        'period' => 'day',
                        'metrics' => $this->metrics($platform, $shape, $takenAt->dayOfYear, $index === array_key_last($days)),
                    ],
                    SnapshotIngestService::TYPE_DEMO,
                );
            }
        }

        app(ComputeMetrics::class)->forTeam($team);

        $this->command?->info("Seeded demo snapshots for team #{$team->id} ({$team->name}).");
    }

    private function team(): Team
    {
        if ($id = env('DEMO_ANALYTICS_TEAM_ID')) {
            $team = Team::findOrFail($id);
        } else {
            $team = Team::where('name', self::TEAM_NAME)->first() ?? User::factory()
                ->withPersonalTeam(fn ($factory) => $factory->state(['name' => self::TEAM_NAME]))
                ->create(['name' => 'Demo owner'])
                ->ownedTeams()->first();
        }

        if (blank($team->infodot_team_uuid)) {
            $team->forceFill(['infodot_team_uuid' => (string) Str::uuid()])->save();
        }

        return $team;
    }

    /**
     * Deterministic, so a re-seed reproduces the same series (and the ingest is idempotent).
     *
     * @param  array<string, mixed>  $shape
     * @return array<string, int|string>
     */
    private function metrics(string $platform, array $shape, int $day, bool $latest): array
    {
        $out = [];

        foreach ($shape as $key => $spec) {
            if ($key === 'currency') {
                $out['currency'] = $spec;

                continue;
            }

            [$base, $swing] = $spec;
            $noise = (crc32("{$platform}.{$key}.{$day}") % 1000) / 1000 - 0.5;
            $wave = sin($day / 4 + strlen($key));
            $value = (int) round($base + $swing * (0.6 * $wave + 0.8 * $noise));

            // One deliberate outlier on the latest day so the anomaly path can be seen locally.
            if ($latest && $platform === 'tasks' && $key === 'overdue') {
                $value = $base * 3;
            }

            $out[$key] = max(0, $value);
        }

        return $out;
    }
}
