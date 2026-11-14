<?php

namespace App\Services\InfoDot;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Asks whether a company still holds this platform (R-50), through IdentityClient::companyPlatforms().
 *
 * The answer is kept ten minutes per company; a failure sixty seconds. Anything that is not a clear answer about
 * this platform's own row (InfoDot down, refusing, a body this app cannot read, the row missing, no secret) is
 * "unknown", and unknown never locks a company out. A failure is logged once per window.
 */
class EntitlementCheck
{
    public const ANSWER_TTL = 600;

    public const FAILURE_TTL = 60;

    public function __construct(private readonly IdentityClient $identity) {}

    public function enabled(): bool
    {
        return (bool) config('ecosystem.infodot.entitlement_check', true);
    }

    /**
     * @return array{entitled: bool, status: string}|null null when InfoDot gave no usable answer
     */
    public function forCompany(string $uuid): ?array
    {
        $key = 'infodot:entitlement:'.$this->platform().':'.$uuid;

        try {
            $cached = Cache::get($key);
        } catch (Throwable) {
            $cached = null;
        }

        if (is_array($cached)) {
            return isset($cached['entitled']) ? ['entitled' => (bool) $cached['entitled'], 'status' => (string) ($cached['status'] ?? '')] : null;
        }

        $answer = $this->ask($uuid);

        if ($answer === null) {
            Log::warning('InfoDot entitlement check gave no answer; carrying on without it.', ['company' => $uuid]);
            $this->remember($key, ['unknown' => true], self::FAILURE_TTL);

            return null;
        }

        $this->remember($key, $answer, self::ANSWER_TTL);

        return $answer;
    }

    /**
     * @return array{entitled: bool, status: string}|null
     */
    private function ask(string $uuid): ?array
    {
        try {
            $read = $this->identity->companyPlatforms($uuid);
        } catch (Throwable) {
            return null;
        }

        if ($read === null) {
            return null;
        }

        foreach ($read['platforms'] as $row) {
            if ($row['key'] === $this->platform()) {
                return ['entitled' => $row['entitled'], 'status' => $row['status']];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function remember(string $key, array $value, int $seconds): void
    {
        try {
            Cache::put($key, $value, $seconds);
        } catch (Throwable) {
            // A cache that cannot be written only means the next request asks again.
        }
    }

    private function platform(): string
    {
        return (string) config('ecosystem.infodot.platform');
    }
}
