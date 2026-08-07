<?php

namespace App\Services;

use App\Models\AnalyticsSnapshot;
use App\Models\BusinessDnaProfile;
use App\Models\Team;
use Illuminate\Support\Carbon;

/**
 * Builds and continuously refines the Business DNA Profile for a team.
 *
 * The DNA profile is the evolving operational fingerprint of an organisation.
 * It learns from every connected platform and becomes more accurate over time,
 * allowing intelligence engines to produce recommendations tailored to this
 * specific organisation rather than relying on generic analytics.
 */
class BusinessDnaService
{
    public function __construct(
        private readonly IntelligenceEngineService $engineService,
        private readonly AiModelRouter $aiRouter,
    ) {}

    /**
     * Compute or update the Business DNA Profile for a team.
     * Returns the updated profile.
     */
    public function computeForTeam(Team $team): BusinessDnaProfile
    {
        $profile = BusinessDnaProfile::firstOrNew(['team_id' => $team->id]);

        $signals = $this->collectSignals($team);
        $context = $this->engineService->buildEcosystemContext($team);

        $dna = $this->derivePatterns($team, $signals, $context);

        $profile->fill([
            'operational_patterns' => $dna['operational_patterns'],
            'seasonal_trends' => $dna['seasonal_trends'],
            'risk_tolerance' => $dna['risk_tolerance'],
            'growth_signals' => $dna['growth_signals'],
            'decision_patterns' => $dna['decision_patterns'],
            'customer_behavior' => $dna['customer_behavior'],
            'bottlenecks' => $dna['bottlenecks'],
            'industry_benchmarks' => $dna['industry_benchmarks'],
            'confidence_score' => $this->calculateConfidence($signals),
            'last_computed_at' => Carbon::now(),
        ]);

        $profile->save();

        return $profile;
    }

    /**
     * Collect all available signal data from connected snapshots.
     */
    private function collectSignals(Team $team): array
    {
        $snapshotCount = AnalyticsSnapshot::where('team_id', $team->id)->count();
        $sourcePlatforms = $team->dataSources()
            ->where('status', 'connected')
            ->pluck('platform')
            ->toArray();

        return [
            'snapshot_count' => $snapshotCount,
            'platform_count' => count($sourcePlatforms),
            'platforms' => $sourcePlatforms,
            'data_age_days' => $this->getOldestSnapshotAgeDays($team),
        ];
    }

    /**
     * Derive behavioural patterns from signals, using AI when available
     * or returning a structured baseline for new profiles.
     */
    private function derivePatterns(Team $team, array $signals, string $context): array
    {
        if ($signals['snapshot_count'] < 1) {
            return $this->baselinePattern($signals['platforms']);
        }

        $prompt = <<<PROMPT
You are building the Business DNA profile for {$team->name}.

{$context}

Based on this ecosystem data, characterise the organisation's operational fingerprint.
Return a JSON object only:
{
  "operational_patterns": { "summary": "...", "key_patterns": ["..."] },
  "seasonal_trends": { "summary": "...", "peaks": ["..."], "troughs": ["..."] },
  "risk_tolerance": { "level": "low|medium|high", "notes": "..." },
  "growth_signals": { "summary": "...", "signals": ["..."] },
  "decision_patterns": { "summary": "...", "tendencies": ["..."] },
  "customer_behavior": { "summary": "...", "segments": ["..."] },
  "bottlenecks": { "summary": "...", "areas": ["..."] },
  "industry_benchmarks": { "notes": "..." }
}
PROMPT;

        $raw = $this->aiRouter->complete($prompt, 'query', $team->id);
        $parsed = json_decode($raw, true);

        if (is_array($parsed) && isset($parsed['operational_patterns'])) {
            return $parsed;
        }

        return $this->baselinePattern($signals['platforms']);
    }

    /**
     * Structural baseline DNA for teams that just connected their first platforms.
     */
    private function baselinePattern(array $platforms): array
    {
        $platformLabels = array_map(
            fn ($p) => IntelligenceEngineService::PLATFORMS[$p]['label'] ?? $p,
            $platforms,
        );

        return [
            'operational_patterns' => [
                'summary' => 'Baseline pattern established. More data needed for precise characterisation.',
                'key_patterns' => [],
            ],
            'seasonal_trends' => [
                'summary' => 'Seasonal trends will emerge after 30+ days of connected data.',
                'peaks' => [],
                'troughs' => [],
            ],
            'risk_tolerance' => [
                'level' => 'medium',
                'notes' => 'Default risk profile. Will refine as decision data accumulates.',
            ],
            'growth_signals' => [
                'summary' => 'Monitoring for growth signals across: '.implode(', ', $platformLabels),
                'signals' => [],
            ],
            'decision_patterns' => [
                'summary' => 'Decision patterns will emerge as workflow and agent data accumulates.',
                'tendencies' => [],
            ],
            'customer_behavior' => [
                'summary' => 'Customer behavioural profiling requires CRM and support data.',
                'segments' => [],
            ],
            'bottlenecks' => [
                'summary' => 'No bottlenecks identified yet. Connect operational platforms for detection.',
                'areas' => [],
            ],
            'industry_benchmarks' => [
                'notes' => 'Industry benchmarks will be applied once the industry profile is set.',
            ],
        ];
    }

    /**
     * Confidence is proportional to how many platforms are connected and
     * how much historical snapshot data is available.
     */
    private function calculateConfidence(array $signals): float
    {
        $platformScore = min($signals['platform_count'] / 5, 1.0) * 0.4;   // max 40% from platforms
        $snapshotScore = min($signals['snapshot_count'] / 100, 1.0) * 0.4; // max 40% from snapshots
        $ageScore = min(($signals['data_age_days'] ?? 0) / 30, 1.0) * 0.2; // max 20% from age

        return round($platformScore + $snapshotScore + $ageScore, 2);
    }

    private function getOldestSnapshotAgeDays(Team $team): int
    {
        $oldest = AnalyticsSnapshot::where('team_id', $team->id)
            ->orderBy('captured_at')
            ->value('captured_at');

        return $oldest ? Carbon::now()->diffInDays($oldest) : 0;
    }
}
