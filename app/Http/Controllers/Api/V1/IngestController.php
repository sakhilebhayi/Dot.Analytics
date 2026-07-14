<?php

namespace App\Http\Controllers\Api\V1;

use App\Jobs\Analytics\IngestPlatformSnapshotJob;
use App\Models\DataSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhook Ingest Controller — v1
 *
 * Allows Dot platforms and external systems to push data snapshots
 * directly to Dot.Analytics rather than being polled.
 *
 * Each request is validated by HMAC-SHA256 signature to ensure
 * only authorised platforms can push data.
 *
 * Route: POST /api/v1/ingest/{platform}
 */
class IngestController extends BaseApiController
{
    /**
     * Receive a data snapshot from a Dot platform.
     *
     * Headers required:
     *   X-Analytics-Signature: sha256=<HMAC-SHA256(secret, raw_body)>
     *   X-Analytics-Platform:  dot.fleet (redundant but explicit)
     *
     * Body: JSON payload of the snapshot data.
     */
    public function receive(Request $request, string $platform): JsonResponse
    {
        // Locate the registered DataSource for this platform + team
        // Team is resolved from the Sanctum token
        $user = $request->user();
        if (! $user) {
            return $this->error('Unauthenticated.', 401);
        }

        $team   = $user->currentTeam;
        $source = DataSource::where('team_id', $team->id)
            ->where('platform', $platform)
            ->where('status', 'connected')
            ->first();

        if (! $source) {
            return $this->error(
                "Platform '{$platform}' is not connected for this organisation.",
                404,
            );
        }

        // Validate HMAC signature if a secret is configured
        $secret = $source->config['webhook_secret'] ?? null;
        if ($secret) {
            if (! $this->validateSignature($request, $secret)) {
                Log::warning('Webhook signature mismatch', [
                    'platform' => $platform,
                    'team_id'  => $team->id,
                    'ip'       => $request->ip(),
                ]);
                return $this->error('Invalid signature.', 401);
            }
        }

        $payload = $request->all();

        if (empty($payload)) {
            return $this->error('Payload is empty.', 422);
        }

        $snapshotType = $request->header('X-Analytics-Snapshot-Type', 'webhook');

        IngestPlatformSnapshotJob::dispatch(
            $source->id,
            $payload,
            $snapshotType,
        );

        return $this->success(
            ['queued' => true, 'platform' => $platform],
            'Snapshot queued for processing.',
            202,
        );
    }

    /**
     * Health check for a platform's webhook configuration.
     * Route: GET /api/v1/ingest/{platform}/ping
     */
    public function ping(Request $request, string $platform): JsonResponse
    {
        $team   = $request->user()->currentTeam;
        $source = DataSource::where('team_id', $team->id)
            ->where('platform', $platform)
            ->first();

        return $this->success([
            'platform'   => $platform,
            'connected'  => $source?->isConnected() ?? false,
            'last_synced' => $source?->last_synced_at?->toIso8601String(),
        ]);
    }

    private function validateSignature(Request $request, string $secret): bool
    {
        $signature = $request->header('X-Analytics-Signature', '');
        $expected  = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }
}
