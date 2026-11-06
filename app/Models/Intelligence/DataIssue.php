<?php

namespace App\Models\Intelligence;

/** Something wrong with the information itself: stale, missing, conflicting or failing. */
class DataIssue extends IntelligenceModel
{
    protected function casts(): array
    {
        return ['first_seen_at' => 'datetime', 'last_seen_at' => 'datetime', 'resolved_at' => 'datetime'];
    }
}
