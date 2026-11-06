<?php

namespace App\Services\Intelligence;

/** The host gives a worker under a minute, so work stops cleanly in time and carries on next run. */
class Deadline
{
    private float $endsAt;

    public function __construct(int $seconds)
    {
        $this->endsAt = microtime(true) + $seconds;
    }

    public function passed(): bool
    {
        return microtime(true) >= $this->endsAt;
    }
}
