<?php

namespace App\Models\Intelligence;

/** One edition of a briefing. Every line in it points at the item or figure it came from. */
class Briefing extends IntelligenceModel
{
    protected function casts(): array
    {
        return ['sections' => 'array', 'published_at' => 'datetime'];
    }
}
