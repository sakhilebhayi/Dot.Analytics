<?php

namespace App\Jobs\Analytics;

use App\Models\ExecutiveBriefing;
use App\Models\Team;
use App\Notifications\ExecutiveBriefingReady;
use App\Services\AiModelRouter;
use App\Services\IntelligenceEngineService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

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

        $recentInsights = $team->crossPlatformInsights()
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn ($i) => "- [{$i->severity}] {$i->title}: {$i->narrative}")
            ->join("\n");

        $prompt = <<<PROMPT
You are Dot.Analytics preparing an executive {$this->period} intelligence briefing for {$team->name}.

Organisation has {$this->countOf($connected)} connected platforms and {$this->countOf(array_keys($activeEngines))} active intelligence engines.

Recent cross-platform insights:
{$recentInsights}

Generate a concise executive briefing. Return JSON only:
{
  "summary": "2-3 sentence executive summary",
  "highlights": ["positive signal 1", "positive signal 2"],
  "risks": ["risk 1", "risk 2"],
  "recommendations": [
    {"title": "...", "rationale": "...", "priority": "high"}
  ]
}
PROMPT;

        $response = $aiRouter->complete($prompt, 'briefing', $this->teamId);
        $parsed = json_decode($response, true);

        $briefing->update([
            'status' => 'ready',
            'summary' => $parsed['summary'] ?? 'Intelligence briefing generated.',
            'highlights' => $parsed['highlights'] ?? [],
            'risks' => $parsed['risks'] ?? [],
            'recommendations' => $parsed['recommendations'] ?? [],
            'engines_consulted' => array_keys($activeEngines),
            'insight_count' => $team->crossPlatformInsights()->count(),
        ]);

        Notification::send($team->allUsers(), new ExecutiveBriefingReady($briefing));
    }

    private function countOf(array $arr): int
    {
        return count($arr);
    }
}
