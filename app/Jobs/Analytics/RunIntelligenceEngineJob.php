<?php

namespace App\Jobs\Analytics;

use App\Events\Analytics\CriticalInsightDiscovered;
use App\Events\Analytics\IntelligenceEngineCompleted;
use App\Models\CrossPlatformInsight;
use App\Models\IntelligenceEngineRun;
use App\Models\Team;
use App\Services\AiModelRouter;
use App\Services\IntelligenceEngineService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

/**
 * Runs a single intelligence engine for a team asynchronously.
 * Dispatched when a platform connects, on a schedule, or manually.
 */
class RunIntelligenceEngineJob implements ShouldQueue
{
    use Queueable;

    public int $tries   = 3;
    public int $timeout = 120;

    public function __construct(
        public readonly int    $teamId,
        public readonly string $engine,
    ) {}

    public function handle(IntelligenceEngineService $engineService, AiModelRouter $aiRouter): void
    {
        $team = Team::find($this->teamId);
        if (! $team) {
            return;
        }

        $engineDef = $engineService->getEngine($this->engine);
        if (! $engineDef) {
            return;
        }

        $run = IntelligenceEngineRun::create([
            'team_id'            => $this->teamId,
            'engine'             => $this->engine,
            'status'             => 'running',
            'platforms_consumed' => $engineDef['sources'],
            'started_at'         => Carbon::now(),
        ]);

        try {
            $connected = $team->dataSources()
                ->where('status', 'connected')
                ->whereIn('platform', $engineDef['sources'])
                ->pluck('platform')
                ->toArray();

            if (empty($connected)) {
                $run->update(['status' => 'completed', 'completed_at' => Carbon::now()]);
                return;
            }

            $insights = $this->generateInsights($team, $engineDef, $connected, $aiRouter);
            $count    = 0;

            foreach ($insights as $insightData) {
                $insight = CrossPlatformInsight::create(array_merge(
                    $insightData,
                    ['team_id' => $this->teamId]
                ));
                $count++;

                if ($insight->severity === 'critical') {
                    CriticalInsightDiscovered::dispatch($insight);
                }
            }

            $run->update([
                'status'             => 'completed',
                'insights_generated' => $count,
                'completed_at'       => Carbon::now(),
            ]);

            IntelligenceEngineCompleted::dispatch(
                $this->teamId,
                $this->engine,
                $count,
                0,
                $connected,
                $run->id,
            );
        } catch (\Throwable $e) {
            $run->update(['status' => 'failed', 'completed_at' => Carbon::now()]);
            throw $e;
        }
    }

    private function generateInsights(Team $team, array $engine, array $connected, AiModelRouter $aiRouter): array
    {
        $produces = implode(', ', $engine['produces'] ?? []);

        $prompt = <<<PROMPT
You are the {$engine['label']} of Dot.Analytics for {$team->name}.

Active data sources: {$team->name} has connected: {$this->csvList($connected)}

Your engine specialises in: {$produces}

Identify 1-2 cross-platform intelligence insights that can ONLY be discovered by
combining data from multiple platforms. Each must trace a causal chain.

Return JSON array only:
[
  {
    "title": "...",
    "narrative": "...",
    "platforms_involved": ["dot.x", "dot.y"],
    "insight_type": "correlation|causation|prediction|risk|opportunity",
    "confidence": 0.85,
    "severity": "info|warning|critical"
  }
]
PROMPT;

        $response = $aiRouter->complete($prompt, 'insight', $this->teamId, $this->engine);
        preg_match('/\[.*\]/s', $response, $matches);
        $parsed = json_decode($matches[0] ?? '[]', true);

        if (is_array($parsed) && count($parsed) > 0) {
            return $parsed;
        }

        return $this->fallback($engine, $connected);
    }

    private function fallback(array $engine, array $connected): array
    {
        return [[
            'title'              => "Signal detected by {$engine['label']}",
            'narrative'          => "Connected platforms (" . $this->csvList($connected) . ") are contributing data. Cross-platform correlations will surface as more historical data accumulates.",
            'platforms_involved' => $connected,
            'insight_type'       => 'correlation',
            'confidence'         => 0.55,
            'severity'           => 'info',
        ]];
    }

    private function csvList(array $items): string
    {
        return implode(', ', $items);
    }
}
