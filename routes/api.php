<?php

use App\Http\Controllers\Api\IngestSnapshotController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\IngestController;
use App\Http\Controllers\Api\V1\IntelligenceController;
use App\Http\Controllers\Api\V1\MetricsController;
use App\Http\Controllers\Api\V1\PlatformController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SavedReportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ─── Liveness probe ───────────────────────────────────────────────────────────
Route::get('/ping', [HealthController::class, 'ping']);

// ─── API Documentation ────────────────────────────────────────────────────────
Route::get('/docs', fn () => response()->json(['spec_url' => url('api-docs/openapi.json'), 'note' => 'Run: php artisan analytics:openapi to generate the spec.']));

// ─── Basic health check (kept for backward compat) ───────────────────────────
Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'service' => 'Dot.Analytics',
    'version' => 'v1',
    'timestamp' => now()->toIso8601String(),
]));

Route::get('/user', fn (Request $request) => $request->user())
    ->middleware('auth:sanctum');

// ─── Server-to-server snapshot ingest ─────────────────────────────────────────
// A Dot platform publishes its own daily snapshot. No user, no Sanctum token: the
// caller proves itself with a per-platform HMAC over "{timestamp}.{raw body}" (see
// ServerIngestSignature) and is rate limited per platform inside the controller.
// The user-token ingest under /v1 below is a different, unchanged path.
Route::post('/ingest/snapshots', IngestSnapshotController::class)->middleware('throttle:analytics-ingest-edge')->name('api.ingest.snapshots');

// ─── API v1 — requires Sanctum token ─────────────────────────────────────────
Route::prefix('v1')
    ->middleware(['auth:sanctum', 'throttle:analytics-api'])
    ->group(function () {

        // Intelligence engines
        Route::prefix('intelligence')->group(function () {
            Route::get('engines', [IntelligenceController::class, 'engines']);
            Route::get('insights', [IntelligenceController::class, 'insights']);
            Route::post('run', [IntelligenceController::class, 'run'])
                ->middleware('throttle:analytics-ai');
        });

        // Platform catalog & connections
        Route::prefix('platforms')->group(function () {
            Route::get('/', [PlatformController::class, 'catalog']);
            Route::get('connected', [PlatformController::class, 'connected']);
            Route::get('{platform}', [PlatformController::class, 'show']);
            Route::post('{platform}/connect', [PlatformController::class, 'connect']);
            Route::post('{platform}/webhook-secret', [PlatformController::class, 'rotateWebhookSecret']);
            Route::delete('{platform}', [PlatformController::class, 'disconnect']);
        });

        // Metrics
        Route::prefix('metrics')->group(function () {
            Route::get('/', [MetricsController::class, 'index']);
            Route::get('definitions', [MetricsController::class, 'definitions']);
            Route::get('ai-usage', [MetricsController::class, 'aiUsage']);
        });

        // Reports & export
        Route::prefix('reports')->group(function () {
            Route::get('{type}', [ReportController::class, 'json']);
            Route::get('{type}/csv', [ReportController::class, 'csv']);
            Route::get('{type}/html', [ReportController::class, 'html']);
        });

        // Webhook ingest — higher rate limit for platform data pushes
        Route::prefix('ingest')->middleware('throttle:analytics-ingest')->group(function () {
            Route::post('{platform}', [IngestController::class, 'receive']);
            Route::get('{platform}/ping', [IngestController::class, 'ping']);
        });

        // Saved report definitions & run history
        Route::prefix('saved-reports')->group(function () {
            Route::get('/', [SavedReportController::class, 'index']);
            Route::post('/', [SavedReportController::class, 'store']);
            Route::post('{id}/run', [SavedReportController::class, 'run']);
            Route::get('{id}/runs', [SavedReportController::class, 'runs']);
            Route::delete('{id}', [SavedReportController::class, 'destroy']);
        });

        // Detailed health diagnostics (auth required for sensitive data)
        Route::get('health/detailed', [HealthController::class, 'detailed'])->middleware('can:operate-platform');

    });
