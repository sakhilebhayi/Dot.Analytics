<?php

namespace App\Data;

/**
 * Immutable data transfer object representing a cross-platform intelligence insight.
 * Used to pass structured insight data between the service layer and persistence.
 */
readonly class CrossPlatformInsightData
{
    public function __construct(
        public string  $title,
        public string  $narrative,
        public array   $platformsInvolved,
        public string  $insightType,    // correlation, causation, prediction, risk, opportunity
        public float   $confidence,
        public string  $severity,       // info, warning, critical
        public ?array  $entitiesInvolved   = null,
        public ?array  $supportingMetrics  = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            title:              $data['title'] ?? '',
            narrative:          $data['narrative'] ?? '',
            platformsInvolved:  $data['platforms_involved'] ?? [],
            insightType:        $data['insight_type'] ?? 'correlation',
            confidence:         (float) ($data['confidence'] ?? 0.7),
            severity:           $data['severity'] ?? 'info',
            entitiesInvolved:   $data['entities_involved'] ?? null,
            supportingMetrics:  $data['supporting_metrics'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'title'               => $this->title,
            'narrative'           => $this->narrative,
            'platforms_involved'  => $this->platformsInvolved,
            'insight_type'        => $this->insightType,
            'confidence'          => $this->confidence,
            'severity'            => $this->severity,
            'entities_involved'   => $this->entitiesInvolved,
            'supporting_metrics'  => $this->supportingMetrics,
        ];
    }

    public function isCritical(): bool
    {
        return $this->severity === 'critical';
    }

    public function isHighConfidence(): bool
    {
        return $this->confidence >= 0.8;
    }
}
