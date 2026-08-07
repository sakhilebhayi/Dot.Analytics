<?php

namespace App\Data;

/**
 * Carries context data for intelligence engine runs.
 * Provides a typed, immutable snapshot of what data is available
 * for the engine to reason about.
 */
readonly class IntelligenceContextData
{
    public function __construct(
        public int $teamId,
        public string $teamName,
        public array $connectedPlatforms,    // ['dot.fleet', 'dot.crm', ...]
        public array $activeEngines,          // engine keys
        public array $recentSnapshots,        // recent data summaries
        public ?array $dnaProfile = null,   // Business DNA profile summary
        public string $period = 'daily',
    ) {}

    /**
     * Describes the context in natural language for AI prompts.
     */
    public function toPromptString(): string
    {
        $platforms = implode(', ', $this->connectedPlatforms);
        $engines = implode(', ', $this->activeEngines);

        $lines = [
            "Organisation: {$this->teamName}",
            "Connected platforms ({$this->platformCount()}): {$platforms}",
            "Active engines ({$this->engineCount()}): {$engines}",
        ];

        if ($this->dnaProfile) {
            $riskLevel = $this->dnaProfile['risk_tolerance']['level'] ?? 'medium';
            $lines[] = "Risk profile: {$riskLevel} tolerance";
        }

        return implode("\n", $lines);
    }

    public function platformCount(): int
    {
        return count($this->connectedPlatforms);
    }

    public function engineCount(): int
    {
        return count($this->activeEngines);
    }

    public function hasPlatform(string $platform): bool
    {
        return in_array($platform, $this->connectedPlatforms, true);
    }
}
