<?php

namespace App\Http\Controllers\Api\V1;

use App\Jobs\Analytics\ComputeMetricsJob;
use App\Models\DataSource;
use App\Models\Team;
use App\Services\Metrics\SnapshotIngestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Snapshot Ingest Controller — v1
 *
 * The one way data enters Dot.Analytics: a Dot platform posts a signed daily
 * snapshot of its own headline numbers. The shape is the metrics contract
 * (docs/metrics-contract.md, config/metrics.php).
 *
 * Authentication, in this order:
 *  1. a Sanctum token of a member of the company's team;
 *  2. the snapshot's company_uuid resolving to that team (teams.infodot_team_uuid);
 *  3. the platform being connected for that team;
 *  4. an HMAC-SHA256 signature of the raw body with the platform's own secret
 *     (mandatory, no unsigned fallback).
 * Steps 1-3 fail with the same generic 404, so a caller cannot tell an unknown
 * company from a company it does not belong to or a platform that is not connected.
 *
 * Route: POST /api/v1/ingest/{platform}
 */
class IngestController extends BaseApiController
{
    public function __construct(private readonly SnapshotIngestService $ingest) {}

    /**
     * Receive a snapshot from a Dot platform.
     *
     * Headers required:
     *   X-Analytics-Signature: sha256=<HMAC-SHA256(secret, raw_body)>
     *
     * Body: {company_uuid, platform, taken_at, period, metrics}. 201 when stored,
     * 200 when the same (platform, taken_at, period) was already stored (the
     * original is returned untouched).
     */
    public function receive(Request $request, string $platform): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->error('Unauthenticated.', 401);
        }

        // Only the signed body counts: a query string is not covered by the signature.
        $body = $request->isJson() ? $request->json()->all() : $request->post();

        $company = $body['company_uuid'] ?? null;
        if (! is_string($company) || ! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $company)) {
            return $this->error('Invalid payload.', 422, ['company_uuid' => ['company_uuid is required and must be a UUID.']]);
        }

        $notConnected = fn () => $this->error("Platform '{$platform}' is not connected for this organisation.", 404);

        $team = Team::where('infodot_team_uuid', strtolower($company))->first();
        if (! $team || ! $user->belongsToTeam($team)) {
            return $notConnected();
        }

        $source = DataSource::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('platform', $platform)
            ->where('status', 'connected')
            ->first();

        if (! $source) {
            return $notConnected();
        }

        // The HMAC is REQUIRED. A bearer token alone is not enough to push data
        // that feeds metrics, alerts and AI insights, and a source without a
        // secret cannot ingest.
        $secret = $source->webhook_secret;
        if (! is_string($secret) || $secret === '') {
            Log::warning('Ingest rejected: no webhook secret configured', [
                'platform' => $platform,
                'team_id' => $team->id,
            ]);

            return $this->error('No webhook secret is configured for this platform. Rotate one before ingesting.', 401);
        }

        if (! $this->validateSignature($request, $secret)) {
            Log::warning('Webhook signature missing or mismatched', [
                'platform' => $platform,
                'team_id' => $team->id,
                'ip' => $request->ip(),
            ]);

            return $this->error('Invalid signature.', 401);
        }

        try {
            $result = $this->ingest->ingest($source, $body, SnapshotIngestService::TYPE_WEBHOOK);
        } catch (ValidationException $e) {
            return $this->error('Invalid snapshot.', 422, $e->errors());
        }

        if (! $result['replayed']) {
            ComputeMetricsJob::dispatch($team->id);
        }

        $snapshot = $result['snapshot'];

        return $this->success([
            'snapshot_id' => $snapshot->id,
            'platform' => $platform,
            'taken_at' => $snapshot->taken_at->utc()->toIso8601String(),
            'period' => $snapshot->period,
            'accepted_keys' => $result['accepted'],
            'dropped_keys' => $result['dropped'],
            'replayed' => $result['replayed'],
        ], $result['replayed'] ? 'Snapshot already received; the original was kept.' : 'Snapshot stored.', $result['replayed'] ? 200 : 201);
    }

    /**
     * Health check for a platform's webhook configuration.
     * Route: GET /api/v1/ingest/{platform}/ping
     */
    public function ping(Request $request, string $platform): JsonResponse
    {
        $this->currentTeam();

        $source = DataSource::where('platform', $platform)->first();

        return $this->success([
            'platform' => $platform,
            'connected' => $source?->isConnected() ?? false,
            'last_synced' => $source?->last_synced_at?->toIso8601String(),
        ]);
    }

    private function validateSignature(Request $request, string $secret): bool
    {
        $signature = $request->header('X-Analytics-Signature');
        if (! is_string($signature) || $signature === '') {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }
}
