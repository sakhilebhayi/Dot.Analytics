<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AiModelUsage;
use App\Models\DataSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * Health Check Controller
 *
 * Provides a detailed health endpoint used by load balancers, Kubernetes probes,
 * and monitoring systems. Returns the status of every critical subsystem.
 *
 * GET /api/health/detailed — full diagnostic (auth required)
 * GET /up                  — Laravel's basic liveness probe (no auth)
 */
class HealthController extends BaseApiController
{
    /**
     * Detailed health check — verifies every subsystem.
     * Returns 200 if healthy, 503 if any critical subsystem is degraded.
     */
    public function detailed(): JsonResponse
    {
        $checks   = [];
        $degraded = false;

        // Database
        $checks['database'] = $this->checkDatabase();
        if (! $checks['database']['healthy']) {
            $degraded = true;
        }

        // Cache
        $checks['cache'] = $this->checkCache();

        // Queue
        $checks['queue'] = $this->checkQueue();

        // AI providers
        $checks['ai_providers'] = $this->checkAiProviders();

        // Application
        $checks['application'] = [
            'healthy'    => true,
            'version'    => config('app.version', '1.0.0'),
            'env'        => config('app.env'),
            'debug'      => config('app.debug'),
            'php_version' => PHP_VERSION,
            'laravel'    => app()->version(),
        ];

        // Intelligence engines
        $checks['intelligence'] = $this->checkIntelligence();

        $status = $degraded ? 503 : 200;

        return response()->json([
            'status'     => $degraded ? 'degraded' : 'healthy',
            'timestamp'  => now()->toIso8601String(),
            'checks'     => $checks,
        ], $status);
    }

    /**
     * Kubernetes liveness probe — fast check, no auth.
     */
    public function ping(): JsonResponse
    {
        return response()->json(['status' => 'ok', 'timestamp' => now()->toIso8601String()]);
    }

    // ─── Individual checks ─────────────────────────────────────────────────

    private function checkDatabase(): array
    {
        $start = microtime(true);
        try {
            DB::select('SELECT 1');
            $latency = (int) ((microtime(true) - $start) * 1000);
            return ['healthy' => true, 'latency_ms' => $latency, 'driver' => config('database.default')];
        } catch (\Throwable $e) {
            return ['healthy' => false, 'error' => $e->getMessage()];
        }
    }

    private function checkCache(): array
    {
        $start = microtime(true);
        try {
            $key = 'health_check_' . uniqid();
            Cache::put($key, true, 5);
            $ok      = Cache::get($key) === true;
            Cache::forget($key);
            $latency = (int) ((microtime(true) - $start) * 1000);
            return ['healthy' => $ok, 'latency_ms' => $latency, 'driver' => config('cache.default')];
        } catch (\Throwable $e) {
            return ['healthy' => false, 'error' => $e->getMessage()];
        }
    }

    private function checkQueue(): array
    {
        try {
            $connection = config('queue.default');
            $size       = Queue::size();
            return [
                'healthy'    => true,
                'connection' => $connection,
                'queue_size' => $size,
            ];
        } catch (\Throwable $e) {
            return ['healthy' => false, 'error' => $e->getMessage()];
        }
    }

    private function checkAiProviders(): array
    {
        $providers = [
            'anthropic' => ! empty(config('services.anthropic.key')),
            'openai'    => ! empty(config('services.openai.key')),
            'google'    => ! empty(config('services.google.ai_key')),
            'deepseek'  => ! empty(config('services.deepseek.key')),
        ];

        $configured = array_keys(array_filter($providers));

        return [
            'healthy'      => ! empty($configured),
            'configured'   => $configured,
            'primary'      => config('services.ai.primary_provider', 'anthropic'),
            'fallback_mode' => empty($configured),
        ];
    }

    private function checkIntelligence(): array
    {
        try {
            $connectedCount = DataSource::where('status', 'connected')->count();
            $aiCallsToday   = AiModelUsage::whereDate('created_at', today())->count();

            return [
                'healthy'             => true,
                'connected_platforms' => $connectedCount,
                'ai_calls_today'      => $aiCallsToday,
            ];
        } catch (\Throwable $e) {
            return ['healthy' => false, 'error' => $e->getMessage()];
        }
    }
}
