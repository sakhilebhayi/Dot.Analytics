<?php

namespace App\Services\Metrics;

use Illuminate\Http\Request;

/**
 * Is this server to server call really from the platform it names, and fresh?
 *
 * The same scheme as Dot.Notify's ingest (and InfoDot's other signed calls): the
 * CALLER signs and this app verifies. Signature = HMAC-SHA256 over
 * "{timestamp}.{raw body}" with the calling platform's own secret, sent as
 * "sha256=<hex>" in X-InfoDot-Signature, with the unix time in
 * X-InfoDot-Timestamp. The timestamp must be within five minutes either way, so a
 * captured request goes stale; the body (company included) cannot be swapped
 * under a signature; and one platform's secret cannot sign for another.
 *
 * The secret is looked up by the platform the body NAMES, so a platform that
 * names another one is checked against that other platform's secret and fails.
 */
class ServerIngestSignature
{
    public const OK = null;

    public const UNCONFIGURED = 'platform_has_no_secret';

    public const MISSING_HEADERS = 'missing_headers';

    public const STALE = 'timestamp_outside_tolerance';

    public const MISMATCH = 'signature_mismatch';

    /**
     * Why the request is not authentic, or null when it is. The reason is for the
     * server log only: callers must answer every refusal with the same response.
     */
    public static function failure(Request $request, string $secret): ?string
    {
        $timestampHeader = (string) $request->header('X-InfoDot-Timestamp');
        $signature = (string) $request->header('X-InfoDot-Signature');

        $timestamp = preg_match('/^\d{1,12}$/', $timestampHeader) ? (int) $timestampHeader : 0;

        // Always do the HMAC, even for an unconfigured platform or missing headers, so a
        // refusal costs the same whatever is wrong with the request.
        $key = $secret !== '' ? $secret : hash('sha256', 'analytics-ingest-unconfigured-platform');
        $expected = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $key);

        // Callers may send the bare hex digest; accept it as well as "sha256=<hex>".
        $given = str_starts_with($signature, 'sha256=') ? $signature : 'sha256='.$signature;

        $matches = hash_equals($expected, $given);
        $fresh = $timestamp !== 0 && abs(now()->timestamp - $timestamp) <= (int) config('analytics.ingest.tolerance', 300);

        return match (true) {
            $secret === '' => self::UNCONFIGURED,
            $timestamp === 0 || $signature === '' => self::MISSING_HEADERS,
            ! $matches => self::MISMATCH,
            ! $fresh => self::STALE,
            default => self::OK,
        };
    }
}
