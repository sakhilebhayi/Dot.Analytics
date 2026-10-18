<?php

namespace App\Services\Metrics;

use App\Models\AnalyticsAlert;
use App\Models\Team;
use Carbon\CarbonInterface;

/**
 * Turns a team's computed metrics and open anomaly alerts into a plain-text block
 * for an AI prompt (briefings, Business DNA), built from MetricReader so the model
 * is told exactly what the dashboard shows: nothing more.
 *
 * A metric with no data is listed as "no data yet" (so the model is told not to
 * guess), a stale one is marked STALE, and demo data is marked. A team with no
 * metric at all has no block and has_data false: callers must not ask a model then.
 */
class MetricsContextBuilder
{
    public function __construct(private readonly MetricReader $reader = new MetricReader) {}

    /**
     * @return array{has_data: bool, block: string, metric_keys: list<string>, alert_ids: list<int>}
     */
    public function build(Team $team, ?CarbonInterface $now = null): array
    {
        $dashboard = $this->reader->dashboard($team, $now);
        $sections = [];
        $keys = [];

        foreach ($dashboard['platforms'] as $platform) {
            if (! $platform['connected']) {
                continue;
            }

            if (! $platform['has_data']) {
                $sections[] = "### {$platform['label']}: connected, no snapshot received yet (NO DATA; draw no conclusions about it)";

                continue;
            }

            $lines = [];
            foreach ($platform['tiles'] as $tile) {
                if ($tile['state'] === 'no_data') {
                    $lines[] = "- {$tile['key']}: no data yet";

                    continue;
                }

                $keys[] = $tile['key'];
                $line = "- {$tile['key']}: {$tile['label']} = {$tile['value']} as of {$tile['as_of']}";
                $line .= $tile['previous'] === null
                    ? ' (first reported point)'
                    : ", previous {$tile['previous']}".($tile['delta'] !== null ? " ({$tile['delta']}".($tile['delta_pct'] !== null ? ", {$tile['delta_pct']}" : '').')' : '');
                $line .= $tile['state'] === 'stale' ? ' [STALE: not reported recently]' : '';
                $line .= $tile['is_demo'] ? ' [DEMO DATA]' : '';
                $lines[] = $line;
            }
            $sections[] = "### {$platform['label']} (computed from its daily snapshots)\n".implode("\n", $lines);
        }

        $alerts = AnalyticsAlert::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('status', 'open')
            ->where('dedupe_key', 'like', 'anomaly:%')
            ->orderByDesc('triggered_at')
            ->limit(10)
            ->get();

        if ($alerts->isNotEmpty()) {
            $sections[] = "### Open anomaly alerts (statistical, from the metrics above)\n"
                .$alerts->map(fn (AnalyticsAlert $a) => "- {$a->title}: {$a->description}")->implode("\n");
        }

        return [
            'has_data' => $keys !== [],
            'block' => implode("\n\n", $sections),
            'metric_keys' => $keys,
            'alert_ids' => $alerts->pluck('id')->sort()->values()->all(),
        ];
    }
}
