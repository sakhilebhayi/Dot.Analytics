<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Metrics\MetricCatalogue;
use App\Services\Metrics\ServerIngestSignature;
use App\Services\Metrics\ServerSnapshotIngest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * POST /api/ingest/snapshots: a Dot platform publishes its own daily snapshot.
 * Server to server; no user, no Sanctum token (see docs/metrics-contract.md).
 *
 * Order matters and is fixed:
 *  1. body size cap;
 *  2. authenticate: the platform named in the body signs "{timestamp}.{raw body}"
 *     with its own secret (config analytics.ingest.secrets). Unknown platform,
 *     blank secret, wrong key, bad or stale signature all return the same generic
 *     401, so a caller learns nothing about which part was wrong;
 *  3. the platform's rate limit (only authenticated calls count, so a stranger
 *     cannot use up a platform's allowance);
 *  4. the contract (422 with field messages);
 *  5. the company: unknown company is a generic 404, and only after authentication.
 *
 * Responses: 201 stored, 200 replay of an already stored snapshot (the original id
 * and numbers), 401, 404, 413, 422, 429.
 */
class IngestSnapshotController extends Controller
{
    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function __invoke(Request $request, ServerSnapshotIngest $ingest, MetricCatalogue $catalogue): JsonResponse
    {
        $raw = $request->getContent();

        if (strlen($raw) > (int) config('analytics.ingest.max_body_bytes', 65536)) {
            return response()->json(['error' => 'payload_too_large'], 413);
        }

        $decoded = json_decode($raw, true);
        $body = is_array($decoded) && ! array_is_list($decoded) ? $decoded : [];

        // The platform that signs must be a real one from the catalogue; its secret is the
        // only one that can authenticate a body naming it.
        $named = is_string($body['platform'] ?? null) ? $body['platform'] : '';
        $platform = $catalogue->hasPlatform($named) ? $named : null;
        $secret = $platform === null ? '' : (string) (config('analytics.ingest.secrets')[$platform] ?? '');

        $failure = ServerIngestSignature::failure($request, $secret);
        if ($failure !== ServerIngestSignature::OK || $platform === null) {
            // The reason is for the log only. Never the signature, the secret or the body.
            Log::warning('Snapshot ingest refused', [
                'reason' => $platform === null ? 'unknown_platform' : $failure,
                'platform' => $platform ?? 'unrecognised',
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'invalid_request'], 401);
        }

        $limitKey = "analytics-ingest:{$platform}";
        $limit = (int) config('analytics.ingest.rate_limit_per_minute', 120);
        if (RateLimiter::tooManyAttempts($limitKey, $limit)) {
            Log::warning('Snapshot ingest rate limited', ['platform' => $platform]);

            return response()->json(['error' => 'rate_limited'], 429, ['Retry-After' => RateLimiter::availableIn($limitKey)]);
        }
        RateLimiter::hit($limitKey, 60);

        $company = $body['company_uuid'] ?? null;
        if (! is_string($company) || ! preg_match(self::UUID, $company)) {
            return $this->invalid(['company_uuid' => ['company_uuid is required and must be a UUID.']]);
        }

        try {
            $result = $ingest->ingest($platform, $body);
        } catch (ValidationException $e) {
            return $this->invalid($e->errors());
        }

        if ($result === null) {
            Log::warning('Snapshot ingest for an unknown company', ['platform' => $platform]);

            return response()->json(['error' => 'not_found'], 404);
        }

        $snapshot = $result['snapshot'];
        $replayed = $result['replayed'];
        $takenAt = $snapshot->taken_at->utc()->toIso8601String();

        return response()->json([
            'success' => true,
            'message' => $replayed ? 'Snapshot already received; the original was kept.' : 'Snapshot stored.',
            'id' => $snapshot->id,
            'status' => $replayed ? 'replayed' : 'stored',
            'replayed' => $replayed,
            'platform' => $platform,
            'taken_at' => $takenAt,
            'period' => $snapshot->period,
            'accepted_keys' => $result['accepted'],
            'dropped_keys' => $result['dropped'],
            'data' => [
                'snapshot_id' => $snapshot->id,
                'platform' => $platform,
                'taken_at' => $takenAt,
                'period' => $snapshot->period,
                'accepted_keys' => $result['accepted'],
                'dropped_keys' => $result['dropped'],
                'replayed' => $replayed,
            ],
        ], $replayed ? 200 : 201);
    }

    /** @param  array<string, list<string>>  $errors */
    private function invalid(array $errors): JsonResponse
    {
        return response()->json(['error' => 'invalid_request', 'errors' => $errors], 422);
    }
}
