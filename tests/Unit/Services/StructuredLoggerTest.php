<?php

namespace Tests\Unit\Services;

use App\Services\StructuredLogger;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class StructuredLoggerTest extends TestCase
{
    public function test_info_logs_with_service_context(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $event, array $context) {
                return $event === 'platform.connected'
                    && $context['service'] === 'intelligence'
                    && $context['team_id'] === 5;
            });

        $logger = new StructuredLogger('intelligence', teamId: 5);
        $logger->info('platform.connected', ['platform' => 'dot.fleet']);
    }

    public function test_warning_calls_log_warning(): void
    {
        Log::shouldReceive('warning')->once();

        $logger = new StructuredLogger('pipeline');
        $logger->warning('pipeline.slow', ['duration_ms' => 5000]);
    }

    public function test_error_includes_exception_details(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function (string $event, array $context) {
                return isset($context['exception'])
                    && $context['exception']['message'] === 'Test error';
            });

        $logger = new StructuredLogger('ai');
        $logger->error('ai.failure', [], new \RuntimeException('Test error'));
    }

    public function test_ai_call_logs_cost_and_tokens(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $event, array $context) {
                return $event === 'ai.model.call'
                    && $context['provider'] === 'anthropic'
                    && $context['cost_usd'] === 0.005;
            });

        $logger = new StructuredLogger('router');
        $logger->aiCall('anthropic', 'claude-sonnet-4-6', 1000, 500, 1200, 0.005);
    }

    public function test_security_adds_security_event_flag(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $event, array $context) {
                return str_starts_with($event, 'security.')
                    && $context['security_event'] === true;
            });

        $logger = new StructuredLogger('api');
        $logger->security('rate_limit_exceeded', ['ip' => '1.2.3.4']);
    }

    public function test_engine_event_prefixes_with_intelligence(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $event, array $context) {
                return $event === 'intelligence.engine.completed'
                    && $context['engine'] === 'financial';
            });

        $logger = new StructuredLogger('intelligence');
        $logger->engineEvent('financial', 'completed', ['insights' => 3]);
    }
}
