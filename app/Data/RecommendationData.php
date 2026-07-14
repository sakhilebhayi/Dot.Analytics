<?php

namespace App\Data;

/**
 * Immutable DTO for AI-generated recommendations.
 * Carries all evidence, confidence, and attribution data
 * required for explainable, auditable AI recommendations.
 */
readonly class RecommendationData
{
    public function __construct(
        public string  $title,
        public string  $rationale,
        public string  $engine,
        public string  $priority,        // critical, high, medium, low
        public float   $confidence,
        public array   $platformsReferenced,
        public ?string $actionLabel  = null,
        public ?string $actionUrl    = null,
        public ?array  $evidence     = null,
        public ?string $aiModel      = null,  // which model generated this
        public ?string $assumptions  = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            title:                $data['title'] ?? '',
            rationale:            $data['rationale'] ?? '',
            engine:               $data['engine'] ?? 'decision',
            priority:             $data['priority'] ?? 'medium',
            confidence:           (float) ($data['confidence'] ?? 0.7),
            platformsReferenced:  $data['platforms_referenced'] ?? [],
            actionLabel:          $data['action_label'] ?? null,
            actionUrl:            $data['action_url'] ?? null,
            evidence:             $data['evidence'] ?? null,
            aiModel:              $data['ai_model'] ?? null,
            assumptions:          $data['assumptions'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'title'                => $this->title,
            'rationale'            => $this->rationale,
            'engine'               => $this->engine,
            'priority'             => $this->priority,
            'confidence'           => $this->confidence,
            'platforms_referenced' => $this->platformsReferenced,
            'action_label'         => $this->actionLabel,
            'action_url'           => $this->actionUrl,
            'supporting_data'      => [
                'evidence'    => $this->evidence,
                'assumptions' => $this->assumptions,
                'ai_model'    => $this->aiModel,
            ],
        ];
    }
}
