<?php

namespace App\Events\Analytics;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BusinessDnaRecomputed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int   $teamId,
        public readonly float $confidenceScore,
        public readonly float $previousConfidenceScore,
    ) {}

    public function confidenceImproved(): bool
    {
        return $this->confidenceScore > $this->previousConfidenceScore;
    }
}
