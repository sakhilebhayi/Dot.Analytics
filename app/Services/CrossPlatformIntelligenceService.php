<?php

namespace App\Services;

use App\Events\Analytics\CriticalInsightDiscovered;
use App\Events\Analytics\IntelligenceEngineCompleted;
use App\Models\CrossPlatformInsight;
use App\Models\IntelligenceEngineRun;
use App\Models\Team;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Generates and persists cross-platform intelligence insights.
 *
 * Integrity contract: an insight exists only if (a) the team has real ingested
 * snapshots inside the window, (b) an AI provider actually analysed them, and
 * (c) the model returned something parseable that cites platforms we have data
 * for. In every other case the engine run is recorded as 'no_data', 'skipped' or
 * 'failed' with a human-readable reason and NOTHING is written as an insight or
 * alert. There is deliberately no canned/fallback text.
 */
class CrossPlatformIntelligenceService
{
    private const INSIGHT_TYPES = ['correlation', 'causation', 'prediction', 'risk', 'opportunity'];

    private const SEVERITIES = ['info', 'warning', 'critical'];

    public function __construct(
        private readonly IntelligenceEngineService $engineService,
        private readonly AiModelRouter $aiRouter,
        private readonly SnapshotContextBuilder $contextBuilder = new SnapshotContextBuilder,
    ) {}

    /**
     * Run every engine that has at least one connected source platform and
     * return the number of insights actually persisted.
     */
    public function runForTeam(Team $team): int
    {
        return array_sum(array_map(
            fn (IntelligenceEngineRun $run) => $run->insights_generated,
            $this->runAllForTeam($team),
        ));
    }

    /**
     * Same as runForTeam() but returns the run records so callers can tell the
     * user which engines were skipped and why.
     *
     * @return list<IntelligenceEngineRun>
     */
    public function runAllForTeam(Team $team): array
    {
        $connected = $team->dataSources()
            ->where('status', 'connected')
            ->pluck('platform')
            ->toArray();

        if (empty($connected)) {
            return [];
        }

        $runs = [];
        foreach (array_keys($this->engineService->getActiveEngines($connected)) as $engineKey) {
            try {
                if ($run = $this->runEngine($team, $engineKey)) {
                    $runs[] = $run;
                }
            } catch (Throwable $e) {
                report($e); // the run row is already marked failed; keep going with other engines
            }
        }

        return $runs;
    }

    /**
     * Run a single engine for a team and record the outcome.
     *
     * @throws Throwable after marking the run failed, so queued callers can retry
     */
    public function runEngine(Team $team, string $engineKey): ?IntelligenceEngineRun
    {
        $engine = $this->engineService->getEngine($engineKey);
        if (! $engine) {
            return null;
        }

        $connected = $team->dataSources()
            ->where('status', 'connected')
            ->whereIn('platform', $engine['sources'])
            ->pluck('platform')
            ->all();

        $run = IntelligenceEngineRun::create([
            'team_id' => $team->id,
            'engine' => $engineKey,
            'status' => 'running',
            'platforms_consumed' => $connected,
            'started_at' => Carbon::now(),
        ]);

        try {
            return $this->execute($team, $engineKey, $engine, $connected, $run);
        } catch (Throwable $e) {
            $this->finish($run, 'failed', 'Unexpected error while running the engine ('.class_basename($e).').');
            throw $e;
        }
    }

    private function execute(Team $team, string $engineKey, array $engine, array $connected, IntelligenceEngineRun $run): IntelligenceEngineRun
    {
        if ($connected === []) {
            return $this->finish($run, 'no_data', 'None of this engine\'s source platforms are connected.');
        }

        $context = $this->contextBuilder->build($team, $connected);
        $provenance = [
            'engine_run_id' => $run->id,
            'engine' => $engineKey,
            'window_start' => $context['window_start'],
            'window_end' => $context['window_end'],
            'snapshot_ids' => $context['snapshot_ids'],
            'platforms_with_data' => $context['platforms_with_data'],
            'platforms_skipped' => $context['platforms_skipped'],
        ];

        if (! $context['has_data']) {
            return $this->finish(
                $run,
                'no_data',
                "No ingested snapshots in the last {$context['window_days']} days for: ".implode(', ', $connected).'. Nothing was analysed.',
                $provenance,
            );
        }

        if (! $this->aiRouter->hasProvider()) {
            return $this->finish(
                $run,
                'skipped',
                'No AI provider is configured (set ANTHROPIC_API_KEY, OPENAI_API_KEY, GOOGLE_AI_KEY or DEEPSEEK_API_KEY). Nothing was generated.',
                $provenance,
            );
        }

        $raw = $this->aiRouter->completeStrict($this->prompt($team, $engine, $context), 'insight', $team->id, $engineKey, 1500);

        if ($raw === null) {
            return $this->finish($run, 'failed', 'The AI provider did not return a response. Nothing was generated.', $provenance);
        }

        $parsed = $this->parse($raw);
        if ($parsed === null) {
            return $this->finish($run, 'failed', 'The AI response could not be parsed as insights. Nothing was stored.', $provenance);
        }

        $count = 0;
        foreach ($parsed as $candidate) {
            $insight = $this->persist($team, $candidate, $context, $provenance);
            if (! $insight) {
                continue;
            }
            $count++;

            if ($insight->severity === 'critical') {
                CriticalInsightDiscovered::dispatch($insight);
            }
        }

        $run->update(['insights_generated' => $count]);
        $reason = $count === 0 ? 'The model found no pattern that the available data supports.' : null;
        $this->finish($run, 'completed', $reason, $provenance);

        $this->announceCompletion($team, $engineKey, $count, $context['platforms_with_data'], $run);

        return $run->refresh();
    }

    /**
     * Push "engine finished" to the dashboard. Real-time is a nicety: with no WebSocket server
     * (Reverb is not running, or cannot run on this host) the broadcast throws, and a finished,
     * recorded run must not turn into a failed job because of it.
     */
    private function announceCompletion(Team $team, string $engineKey, int $count, array $platforms, IntelligenceEngineRun $run): void
    {
        try {
            IntelligenceEngineCompleted::dispatch($team->id, $engineKey, $count, 0, $platforms, $run->id);
        } catch (Throwable $e) {
            // Class only: the message can embed the broadcaster's request URL.
            Log::warning('Real-time engine-completed push skipped.', ['exception' => class_basename($e)]);
        }
    }

    private function finish(IntelligenceEngineRun $run, string $status, ?string $reason, ?array $provenance = null): IntelligenceEngineRun
    {
        $run->update(array_filter([
            'status' => $status,
            'status_reason' => $reason,
            'provenance' => $provenance,
            'completed_at' => Carbon::now(),
        ], fn ($v) => $v !== null));

        return $run->refresh();
    }

    private function prompt(Team $team, array $engine, array $context): string
    {
        $produces = implode(', ', $engine['produces'] ?? []);
        $allowed = implode(', ', $context['platforms_with_data']);

        return <<<PROMPT
You are the {$engine['label']} of Dot.Analytics for {$team->name}.
Your engine specialises in: {$produces}

Below is the ONLY data you may use: real snapshots pushed by the connected platforms
between {$context['window_start']} and {$context['window_end']}.

{$context['block']}

Rules:
- Base every statement on values that appear above. Do NOT invent numbers, people, events or platforms.
- A platform marked NO DATA must not appear in any insight.
- Only cite platforms from this list in "platforms_involved": {$allowed}
- If the data does not support a genuine insight, return an empty array [].
- Prefer insights that combine more than one platform when the data supports it.

Return a JSON array only, no markdown (at most 2 items):
[
  {
    "title": "...",
    "narrative": "... quote the figures you relied on ...",
    "platforms_involved": ["dot.x"],
    "insight_type": "correlation|causation|prediction|risk|opportunity",
    "confidence": 0.0,
    "severity": "info|warning|critical",
    "evidence_snapshot_ids": [1, 2]
  }
]
PROMPT;
    }

    /**
     * @return list<array<string, mixed>>|null null when the output is not a JSON array
     */
    private function parse(string $raw): ?array
    {
        if (! preg_match('/\[.*\]/s', $raw, $matches)) {
            return null;
        }

        $decoded = json_decode($matches[0], true);

        return is_array($decoded) && array_is_list($decoded) ? $decoded : null;
    }

    /**
     * Validate one model-proposed insight against the data we actually had.
     */
    private function persist(Team $team, mixed $candidate, array $context, array $provenance): ?CrossPlatformInsight
    {
        if (! is_array($candidate)) {
            return null;
        }

        $title = trim((string) ($candidate['title'] ?? ''));
        $narrative = trim((string) ($candidate['narrative'] ?? ''));
        if ($title === '' || $narrative === '') {
            return null;
        }

        $platforms = array_values(array_intersect(
            array_filter((array) ($candidate['platforms_involved'] ?? []), 'is_string'),
            $context['platforms_with_data'],
        ));
        if ($platforms === []) {
            return null; // cites nothing we hold data for
        }

        $evidence = array_values(array_intersect(
            array_filter((array) ($candidate['evidence_snapshot_ids'] ?? []), 'is_int'),
            $context['snapshot_ids'],
        ));

        $type = in_array($candidate['insight_type'] ?? null, self::INSIGHT_TYPES, true) ? $candidate['insight_type'] : 'correlation';
        $severity = in_array($candidate['severity'] ?? null, self::SEVERITIES, true) ? $candidate['severity'] : 'info';
        $confidence = is_numeric($candidate['confidence'] ?? null) ? max(0.0, min(1.0, (float) $candidate['confidence'])) : 0.5;

        return CrossPlatformInsight::create([
            'team_id' => $team->id,
            'title' => mb_substr($title, 0, 255),
            'narrative' => $narrative,
            'platforms_involved' => $platforms,
            'insight_type' => $type,
            'confidence' => $confidence,
            'severity' => $severity,
            'provenance' => array_merge($provenance, [
                'snapshot_ids' => $evidence !== [] ? $evidence : $context['snapshot_ids'],
            ]),
        ]);
    }
}
