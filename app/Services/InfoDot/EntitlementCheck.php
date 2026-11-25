<?php

namespace App\Services\InfoDot;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Asks whether a company still holds this platform and whether InfoDot has it switched on (R-50, R-61).
 *
 * One read of InfoDot's company platforms endpoint per company, kept for 60 seconds, so a switch made at InfoDot
 * (a platform put in maintenance) takes effect within about a minute. The answer says whether the company holds the
 * platform (`entitled`, with InfoDot's `status` for the wording) and whether InfoDot has the platform switched on at all
 * (`available`, `message`, `features`; R-61 availability contract, as Dot.Tasks). Every clear answer is also kept for
 * six hours. Anything that is not a clear answer about this platform's own row (InfoDot down, refusing, a body this
 * app cannot read, the row missing, no secret) is "unknown": the last clear answer under six hours old is used, so a
 * company last told "unavailable" or "not held" stays that way through an InfoDot outage; with none ever stored the
 * caller carries on. An outage never locks out a company that was last told it could work. A failure is logged once per
 * window. Only the company's uuid is sent; nothing about a person.
 *
 * This reads the endpoint itself (not IdentityClient::companyPlatforms, whose trimmed rows feed the business platforms
 * panel and carry none of the R-61 fields). The panel keeps its own read.
 */
class EntitlementCheck
{
    /** How long an answer is used before InfoDot is asked again. */
    public const ANSWER_TTL = 60;

    /** How long a failure to get any answer is remembered. */
    public const FAILURE_TTL = 60;

    /** How long the last clear answer is used when InfoDot cannot be reached. */
    public const LAST_KNOWN_TTL = 21600;

    public function enabled(): bool
    {
        return (bool) config('ecosystem.infodot.entitlement_check', true);
    }

    /**
     * @return array{entitled: bool, status: string, available: bool, message: ?string, features: array<string, array{state: string, message: ?string}>}|null null when InfoDot gave no clear answer and none is remembered
     */
    public function forCompany(string $uuid): ?array
    {
        $key = 'infodot:entitlement:'.$this->platform().':'.$uuid;

        try {
            $cached = Cache::get($key);
        } catch (Throwable) {
            $cached = null;
        }

        // An entry written before the availability contract (no `answer`, or an answer with no `available`) is a miss,
        // never read as "available".
        if (is_array($cached) && array_key_exists('answer', $cached)
            && ($cached['answer'] === null || (is_array($cached['answer']) && array_key_exists('available', $cached['answer'])))) {
            return $cached['answer'];
        }

        $answer = $this->ask($uuid);

        try {
            if ($answer !== null) {
                Cache::put($key.':last', ['answer' => $answer, 'at' => time()], self::LAST_KNOWN_TTL);
            } else {
                $last = Cache::get($key.':last');
                $answer = is_array($last) && is_array($last['answer'] ?? null) && array_key_exists('available', $last['answer'])
                    && time() - (int) ($last['at'] ?? 0) < self::LAST_KNOWN_TTL ? $last['answer'] : null;
            }

            Cache::put($key, ['answer' => $answer], $answer === null ? self::FAILURE_TTL : self::ANSWER_TTL);
        } catch (Throwable) {
            // No cache, no memory of the answer: the next request asks again.
        }

        return $answer;
    }

    /**
     * @return array{entitled: bool, status: string, available: bool, message: ?string, features: array<string, array{state: string, message: ?string}>}|null
     */
    private function ask(string $uuid): ?array
    {
        $secret = config('ecosystem.infodot.secret');
        $url = $this->url($uuid);
        $caller = $this->platform();

        if (! is_string($secret) || $secret === '' || $url === null || $caller === '' || ! Str::isUuid($uuid)) {
            return $this->unknown('not configured', $uuid);
        }

        try {
            $response = Http::withToken($secret)
                ->withHeaders(['X-Dot-Platform' => $caller])
                ->acceptJson()
                ->timeout($this->timeout())
                ->get($url, ['platform' => $caller]);

            if (! $response->successful()) {
                return $this->unknown('status '.$response->status(), $uuid);
            }

            $body = $response->json();
        } catch (Throwable $e) {
            return $this->unknown(class_basename($e), $uuid);
        }

        $rows = is_array($body) ? ($body['platforms'] ?? null) : null;

        if (! is_array($rows) || ($body['company']['uuid'] ?? null) !== $uuid) {
            return $this->unknown('malformed body', $uuid);
        }

        foreach ($rows as $row) {
            if (is_array($row) && ($row['key'] ?? null) === $caller) {
                if (! is_bool($row['entitled'] ?? null)) {
                    return $this->unknown('malformed body', $uuid);
                }

                return [
                    'entitled' => $row['entitled'],
                    'status' => is_string($row['status'] ?? null) ? $row['status'] : '',
                    // R-61: an InfoDot from before contract 2 gives no `available`, which is not a switch-off.
                    'available' => ($row['available'] ?? true) !== false,
                    'message' => is_string($row['message'] ?? null) && $row['message'] !== '' ? mb_substr($row['message'], 0, 400) : null,
                    'features' => self::features($row['features'] ?? null),
                ];
            }
        }

        return $this->unknown('own row missing', $uuid);
    }

    /**
     * The feature switches in the caller's own row (R-61), kept as key => state and message. Anything malformed is
     * ignored, and a feature that is not listed is available.
     *
     * @return array<string, array{state: string, message: ?string}>
     */
    private static function features(mixed $raw): array
    {
        $out = [];

        foreach (is_array($raw) ? $raw : [] as $key => $row) {
            if (is_string($key) && is_array($row) && is_string($row['state'] ?? null)) {
                $out[$key] = ['state' => $row['state'], 'message' => is_string($row['message'] ?? null) && $row['message'] !== '' ? mb_substr($row['message'], 0, 400) : null];
            }
        }

        return $out;
    }

    /**
     * InfoDot's read endpoint. ANALYTICS_ENTITLEMENT_URL sets it outright ({uuid} is replaced by the company's uuid);
     * otherwise INFODOT_COMPANIES_URL, or the redeem address, as the business platforms panel resolves it.
     */
    private function url(string $uuid): ?string
    {
        $configured = config('ecosystem.infodot.entitlement_url');

        if (is_string($configured) && $configured !== '') {
            return str_replace('{uuid}', rawurlencode($uuid), $configured);
        }

        $explicit = (string) config('ecosystem.infodot.companies_url');
        $redeem = (string) config('ecosystem.infodot.redeem_url');

        $base = $explicit !== ''
            ? rtrim($explicit, '/')
            : (preg_match('#/redeem/?$#', $redeem) === 1 ? (string) preg_replace('#/redeem/?$#', '/companies', $redeem) : null);

        return $base === null ? null : $base.'/'.$uuid.'/platforms';
    }

    /** Asked inside a page load, so it waits less than sign-in does, unless ANALYTICS_ENTITLEMENT_TIMEOUT sets it. */
    private function timeout(): int
    {
        $set = config('ecosystem.infodot.entitlement_timeout');

        return is_numeric($set) && (int) $set > 0 ? (int) $set : min(3, max(1, (int) config('ecosystem.infodot.timeout', 5)));
    }

    /** Logged where the failure is stored, so once per cache window. Never the secret, never a person. */
    private function unknown(string $why, string $uuid): null
    {
        Log::warning('InfoDot entitlement check gave no answer; carrying on unless a last clear answer is remembered.', ['company' => $uuid, 'reason' => $why]);

        return null;
    }

    private function platform(): string
    {
        return (string) config('ecosystem.infodot.platform');
    }
}
