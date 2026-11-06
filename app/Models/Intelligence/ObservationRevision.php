<?php

namespace App\Models\Intelligence;

/** A source changed a value it had already published. The old value stays on record. */
class ObservationRevision extends IntelligenceModel
{
    protected function casts(): array
    {
        return ['revised_at' => 'datetime'];
    }
}
