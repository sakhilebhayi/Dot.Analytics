<?php

namespace App\Jobs\Analytics;

use App\Models\ExecutiveBriefing;
use App\Models\Team;
use App\Services\AiModelRouter;
use App\Services\IntelligenceEngineService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Generates an AI-powered executive intelligence briefing for a team.
 * Briefings are created daily, weekly, and monthly and summarise
 * the most important signals, risks, and recommended actions.
 */
class GenerateExecutiveBriefingJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 180;

    public function __construct(
        public readonly int $teamId,
        public readonly string $period = 'weekly',
        public readonly string $periodDate = '',
    ) {}

    public function handle(AiModelRouter $aiRouter, IntelligenceEngineService $engineService): void
    {
        $team = Team::find($this->teamId);
        if (! $team) {
            return;
        }

        $date = $this->periodDate ?: now()->toDateString();

        $briefing = ExecutiveBriefing::firstOrCreate(
            ['team_id' => $this->teamId, 'period' => $this->period, 'period_date' => $date],
            ['status' => 'generating'],
        );

        if ($briefing->status === 'ready') {
            return; // Already generated
        }

        $connected = $team->dataSources()->where('status', 'connected')->pluck('platform')->toArray();
        $activeEngines = $engineService->getActiveEngines($connected);

        $insights = $team->crossPlatformInsights()
            ->whereIn('status', ['new', 'reviewed'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        // A briefing summarises insights that were derived from real data. With
        // none (or no provider) there is nothing truthful to say: record that the
        // briefing is unavailable rather than storing placeholder text as one.
        if ($insights->isEmpty()) {
            $this->markUnavailable($briefing, 'No data connected yet: there are no insights to summarise. Connect a platform, let it push data, then run the engines.');

            return;
        }

        if (! $aiRouter->hasProvider()) {
            $this->markUnavailable($briefing, 'No AI provider is configured, so a briefing cannot be written.');

            return;
        }

        $recentInsights = $insights
            ->map(fn ($i) => "- [{$i->severity}] {$i->title}: {$i->narrative}")
            ->join("\n");

        $prompt = <<<PROMPT
You are Dot.Analytics preparing an executive {$this->period} intelligence briefing for {$team->name}.

Organisation has {$this->countOf($connected)} connected platforms and {$this->countOf(array_keys($activeEngines))} active intelligence engines.

Insights derived from the organisation's real ingested data (use ONLY these; do not invent facts):
{$recentInsights}

Generate a concise executive briefing grounded strictly in the insights above. Return JSON only:
{
  "summary": "2-3 sentence executive summary",
  "highlights": ["positive signal 1", "positive signal 2"],
  "risks": ["risk 1", "risk 2"],
  "recommendations": [
    {"title": "...", "rationale": "...", "priority": "high"}
  ]
}
PROMPT;

        $response = $aiRouter->completeStrict($prompt, 'briefing', $this->teamId);

        if ($response === null) {
            $this->markUnavailable($briefing, 'The AI provider did not return a response.');

            return;
        }

        $parsed = $this->parseJsonObject($response);
        if (! $parsed || trim((string) ($parsed['summary'] ?? '')) === '') {
            $this->markUnavailable($briefing, 'The AI response could not be parsed as a briefing.');

            return;
        }

        $briefing->update([
            'status' => 'ready',
            'status_reason' => null,
            'summary' => $parsed['summary'],
            'highlights' => is_array($parsed['highlights'] ?? null) ? $parsed['highlights'] : [],
            'risks' => is_array($parsed['risks'] ?? null) ? $parsed['risks'] : [],
            'recommendations' => is_array($parsed['recommendations'] ?? null) ? $parsed['recommendations'] : [],
            'engines_consulted' => array_keys($activeEngines),
            'insight_count' => $insights->count(),
            'provenance' => [
                'insight_ids' => $insights->pluck('id')->sort()->values()->all(),
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    private function markUnavailable(ExecutiveBriefing $briefing, string $reason): void
    {
        $briefing->update([
            'status' => 'unavailable',
            'status_reason' => $reason,
            'summary' => null,
            'highlights' => [],
            'risks' => [],
            'recommendations' => [],
        ]);
    }

    /** @return array<string, mixed>|null */
    private function parseJsonObject(string $raw): ?array
    {
        if (! preg_match('/\{.*\}/s', $raw, $m)) {
            return null;
        }
        $decoded = json_decode($m[0], true);

        return is_array($decoded) ? $decoded : null;
    }

    private function countOf(array $arr): int
    {
        return count($arr);
    }
}
