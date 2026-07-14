<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Structured Logger
 *
 * Provides consistent, machine-parseable structured logging across
 * all Dot.Analytics services. Every log entry includes:
 *  - service context (service, team_id, user_id)
 *  - correlation ID for distributed tracing
 *  - structured key-value pairs
 *
 * Enables integration with log aggregation tools (Datadog, Loki, ELK).
 */
class StructuredLogger
{
    public function __construct(
        private readonly string $service,
        private readonly ?int   $teamId  = null,
        private readonly ?int   $userId  = null,
    ) {}

    public function info(string $event, array $context = []): void
    {
        Log::info($event, $this->enrich($context));
    }

    public function warning(string $event, array $context = []): void
    {
        Log::warning($event, $this->enrich($context));
    }

    public function error(string $event, array $context = [], ?\Throwable $exception = null): void
    {
        $enriched = $this->enrich($context);

        if ($exception) {
            $enriched['exception'] = [
                'class'   => get_class($exception),
                'message' => $exception->getMessage(),
                'file'    => $exception->getFile(),
                'line'    => $exception->getLine(),
            ];
        }

        Log::error($event, $enriched);
    }

    public function critical(string $event, array $context = [], ?\Throwable $exception = null): void
    {
        $enriched = $this->enrich($context);

        if ($exception) {
            $enriched['exception'] = [
                'class'   => get_class($exception),
                'message' => $exception->getMessage(),
                'trace'   => $exception->getTraceAsString(),
            ];
        }

        Log::critical($event, $enriched);
    }

    /**
     * Log an intelligence engine event with timing information.
     */
    public function engineEvent(string $engine, string $event, array $context = []): void
    {
        $this->info("intelligence.engine.{$event}", array_merge([
            'engine' => $engine,
        ], $context));
    }

    /**
     * Log an AI model call with cost and latency tracking.
     */
    public function aiCall(string $provider, string $model, int $inputTokens, int $outputTokens, int $latencyMs, float $costUsd): void
    {
        $this->info('ai.model.call', [
            'provider'      => $provider,
            'model'         => $model,
            'input_tokens'  => $inputTokens,
            'output_tokens' => $outputTokens,
            'latency_ms'    => $latencyMs,
            'cost_usd'      => $costUsd,
        ]);
    }

    /**
     * Log a security event (auth failure, rate limit, suspicious activity).
     */
    public function security(string $event, array $context = []): void
    {
        $enriched = $this->enrich($context);
        $enriched['security_event'] = true;
        Log::warning("security.{$event}", $enriched);
    }

    private function enrich(array $context): array
    {
        return array_merge([
            'service'    => $this->service,
            'team_id'    => $this->teamId,
            'user_id'    => $this->userId,
            'request_id' => request()->header('X-Request-ID') ?? request()->header('X-Correlation-ID'),
            'timestamp'  => now()->toIso8601String(),
        ], $context);
    }
}
