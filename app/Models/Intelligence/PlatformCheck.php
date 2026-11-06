<?php

namespace App\Models\Intelligence;

/** Whether a platform answered the last time we asked. A link is only offered to a visitor while it does. */
class PlatformCheck extends IntelligenceModel
{
    protected function casts(): array
    {
        return ['checked_at' => 'datetime', 'last_ok_at' => 'datetime'];
    }
}
