<?php

namespace App\Services;

use App\Models\AnalyticsSnapshot;
use App\Models\BusinessDnaProfile;
use App\Models\Team;
use App\Services\Metrics\MetricsContextBuilder;
use Illuminate\Support\Carbon;

/**
 * Builds and refines the Business DNA Profile for a team, from real data only.
 *
 * The profile is an AI characterisation of the team's real snapshots and computed
 * metrics. Integrity contract, like the intelligence engines:
 *  - no snapshots for any connected roster platform: nothing is computed and nothing
 *    is stored ("no data yet"); there is no structural baseline text any more;
 *  - no AI provider, or an answer that cannot be parsed: nothing is stored, and a
 *    profile that already exists is left exactly as it was;
 *  - a field the model did not return stays empty rather than receiving default prose.
 */
class BusinessDnaService
{
    public function __construct(
        private readonly IntelligenceEngineService $engineService,
        private readonly AiModelRouter $aiRouter,
        private readonly SnapshotContextBuilder $snapshots = new SnapshotContextBuilder,
        private readonly MetricsContextBuilder $metrics = new MetricsContextBuilder,
    ) {}

    /**
     * Compute or update the profile. Returns the profile, or null when there is
     * nothing real to base one on (and none exists already).
     */
    public function computeForTeam(Team $team): ?BusinessDnaProfile
    {
        $existing = BusinessDnaProfile::withoutGlobalScopes()->where('team_id', $team->id)->first();

        $signals = $this->collectSignals($team);
        if ($signals['snapshot_count'] < 1) {
            return $existing;
        }

        $dna = $this->derivePatterns($team, $signals);
        if ($dna === null) {
            return $existing;
        }

        $profile = $existing ?? new BusinessDnaProfile(['team_id' => $team->id]);
        $profile->fill([
            'operational_patterns' => $dna['operational_patterns'],
            'seasonal_trends' => $dna['seasonal_trends'] ?? null,
            'risk_tolerance' => $dna['risk_tolerance'] ?? null,
            'growth_signals' => $dna['growth_signals'] ?? null,
            'decision_patterns' => $dna['decision_patterns'] ?? null,
            'customer_behavior' => $dna['customer_behavior'] ?? null,
            'bottlenecks' => $dna['bottlenecks'] ?? null,
            'industry_benchmarks' => $dna['industry_benchmarks'] ?? null,
            'confidence_score' => $this->calculateConfidence($signals),
            'last_computed_at' => Carbon::now(),
        ]);
        $profile->save();

        return $profile;
    }

    /**
     * The real data available: snapshots of connected roster platforms.
     */
    private function collectSignals(Team $team): array
    {
        $platforms = $team->dataSources()
            ->where('status', 'connected')
            ->whereIn('platform', array_keys(IntelligenceEngineService::PLATFORMS))
            ->pluck('platform')
            ->toArray();

        $snapshotCount = $platforms === [] ? 0 : AnalyticsSnapshot::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->whereHas('dataSource', fn ($q) => $q->withoutGlobalScopes()->where('team_id', $team->id)->whereIn('platform', $platforms))
            ->count();

        return [
            'snapshot_count' => $snapshotCount,
            'platform_count' => count($platforms),
            'platforms' => $platforms,
            'data_age_days' => $this->getOldestSnapshotAgeDays($team),
        ];
    }

    /**
     * Ask the model to characterise the real data. Null when there is no
     * provider or its answer is not a usable profile.
     */
    private function derivePatterns(Team $team, array $signals): ?array
    {
        $context = $this->engineService->buildEcosystemContext($team);
        $snapshotContext = $this->snapshots->build($team, $signals['platforms']);
        $metricsContext = $this->metrics->build($team);

        $prompt = <<<PROMPT
You are building the Business DNA profile for {$team->name}.

{$context}

Computed metrics (real, from the platforms' daily snapshots):
{$metricsContext['block']}

Recent snapshots (real):
{$snapshotContext['block']}

Characterise the organisation's operational fingerprint using ONLY the data above. If the data does not support a field, leave it out or set it to null; do not invent patterns, seasons, customers or benchmarks.
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

        $raw = $this->aiRouter->completeStrict($prompt, 'query', $team->id);
        if ($raw === null) {
            return null;
        }

        $parsed = preg_match('/\{.*\}/s', $raw, $m) ? json_decode($m[0], true) : null;

        return is_array($parsed) && is_array($parsed['operational_patterns'] ?? null) ? $parsed : null;
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
        $oldest = AnalyticsSnapshot::withoutGlobalScopes()->where('team_id', $team->id)
            ->orderBy('captured_at')
            ->value('captured_at');

        return $oldest ? (int) Carbon::now()->diffInDays($oldest, true) : 0;
    }
}
