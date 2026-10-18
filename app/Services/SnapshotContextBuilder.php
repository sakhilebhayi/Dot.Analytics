<?php

namespace App\Services;

use App\Models\AnalyticsSnapshot;
use App\Models\Team;
use Illuminate\Support\Carbon;

/**
 * Turns a team's REAL ingested AnalyticsSnapshot rows into a bounded text block
 * for AI prompts, and records exactly which snapshots / window were used so the
 * resulting insight can carry provenance.
 *
 * A platform with no snapshot in the window is not summarised: it is reported as
 * skipped, so the model is told there is no data for it instead of being left to
 * invent some.
 */
class SnapshotContextBuilder
{
    public const WINDOW_DAYS = 30;

    private const SNAPSHOTS_PER_PLATFORM = 5;

    private const TOTAL_CHAR_CAP = 12000;

    private const PLATFORM_CHAR_CAP = 2500;

    private const STRING_CAP = 200;

    private const LIST_CAP = 10;

    private const DEPTH_CAP = 4;

    /**
     * @param  list<string>  $platforms  Connected platform keys to consider.
     * @return array{
     *     has_data: bool,
     *     block: string,
     *     snapshot_ids: list<int>,
     *     platforms_with_data: list<string>,
     *     platforms_skipped: list<string>,
     *     window_start: string,
     *     window_end: string,
     *     window_days: int
     * }
     */
    public function build(Team $team, array $platforms, int $windowDays = self::WINDOW_DAYS): array
    {
        $end = Carbon::now();
        $start = $end->copy()->subDays($windowDays);

        $snapshots = AnalyticsSnapshot::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('captured_at', '>=', $start)
            ->whereHas('dataSource', fn ($q) => $q->withoutGlobalScopes()
                ->where('team_id', $team->id)
                ->whereIn('platform', $platforms))
            ->with(['dataSource' => fn ($q) => $q->withoutGlobalScopes()])
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy(fn (AnalyticsSnapshot $s) => $s->dataSource->platform);

        $withData = array_values(array_filter($platforms, fn ($p) => $snapshots->has($p)));
        $skipped = array_values(array_diff($platforms, $withData));

        $perPlatformCap = $withData === []
            ? self::PLATFORM_CHAR_CAP
            : min(self::PLATFORM_CHAR_CAP, intdiv(self::TOTAL_CHAR_CAP, count($withData)));

        $ids = [];
        $sections = [];

        foreach ($withData as $platform) {
            $latest = $snapshots->get($platform)->take(self::SNAPSHOTS_PER_PLATFORM);
            $lines = [];

            foreach ($latest as $snapshot) {
                $ids[] = $snapshot->id;
                $payload = $snapshot->payload;
                unset($payload['_quality']);
                $lines[] = sprintf(
                    '- snapshot #%d (%s, %s): %s',
                    $snapshot->id,
                    $snapshot->snapshot_type,
                    $snapshot->captured_at->toIso8601String(),
                    json_encode($this->compact($payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR),
                );
            }

            $body = implode("\n", $lines);
            if (mb_strlen($body) > $perPlatformCap) {
                $body = mb_substr($body, 0, $perPlatformCap).' …[truncated]';
            }

            $sections[] = "### {$platform} — {$latest->count()} most recent of {$snapshots->get($platform)->count()} snapshot(s) in the window\n{$body}";
        }

        foreach ($skipped as $platform) {
            $sections[] = "### {$platform} — no snapshots in the last {$windowDays} days (NO DATA; draw no conclusions about this platform)";
        }

        sort($ids);

        return [
            'has_data' => $withData !== [],
            'block' => implode("\n\n", $sections),
            'snapshot_ids' => $ids,
            'platforms_with_data' => $withData,
            'platforms_skipped' => $skipped,
            'window_start' => $start->toIso8601String(),
            'window_end' => $end->toIso8601String(),
            'window_days' => $windowDays,
        ];
    }

    /**
     * Shrink a payload: cap string length, list length and nesting depth.
     */
    private function compact(mixed $value, int $depth = 0): mixed
    {
        if (is_string($value)) {
            return mb_strlen($value) > self::STRING_CAP ? mb_substr($value, 0, self::STRING_CAP).'…' : $value;
        }

        if (! is_array($value)) {
            return $value;
        }

        if ($depth >= self::DEPTH_CAP) {
            return '…['.count($value).' nested item(s)]';
        }

        $isList = array_is_list($value);
        $out = [];
        $kept = 0;

        foreach ($value as $key => $item) {
            if ($kept >= self::LIST_CAP) {
                $out[$isList ? count($out) : '…'] = '+'.(count($value) - $kept).' more';
                break;
            }
            $out[$key] = $this->compact($item, $depth + 1);
            $kept++;
        }

        return $out;
    }
}
