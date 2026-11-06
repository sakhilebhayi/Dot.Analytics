<?php

namespace App\Services\Library;

use App\Models\User;

/** What a person may keep. Limits protect storage and the inbox; they never hide a published figure. */
final class Entitlements
{
    public function __construct(private readonly string $plan = 'free') {}

    public static function for(User $user): self
    {
        // No billing exists yet, so no one is on anything but the free plan.
        return new self('free');
    }

    public function plan(): array
    {
        return (array) config('intelligence_plans.plans.'.$this->plan);
    }

    public function watches(): int
    {
        return (int) ($this->plan()['watches'] ?? 0);
    }

    public function saved(): int
    {
        return (int) ($this->plan()['saved'] ?? 0);
    }

    public function alertsKept(): int
    {
        return (int) ($this->plan()['alerts_kept'] ?? 0);
    }
}
