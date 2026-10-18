<?php

namespace Tests\Unit\Metrics;

use Tests\TestCase;

/**
 * The server-to-server ingest is only as honest as its configuration and its page:
 * every platform in the catalogue has an env name and a secret slot (blank by
 * default), nothing else does, and the worked example in the docs is a real HMAC.
 */
class ServerIngestConfigTest extends TestCase
{
    // The worked example printed in docs/metrics-contract.md. Not a real secret.
    private const SECRET = 'example-tasks-secret-do-not-use';

    private const TIMESTAMP = '1792400000';

    private const BODY = '{"company_uuid":"3f2b8c1e-9a4d-4e7b-8c55-1d2e3f4a5b6c","platform":"tasks","taken_at":"2026-10-17T06:00:00Z","period":"day","metrics":{"open":12,"overdue":3}}';

    public function test_there_is_exactly_one_secret_slot_per_catalogue_platform_and_it_is_blank_by_default(): void
    {
        $slots = config('analytics.ingest.secrets');

        $this->assertEqualsCanonicalizing(array_keys(config('metrics.platforms')), array_keys($slots));
        $this->assertCount(11, $slots);
        foreach ($slots as $platform => $secret) {
            $this->assertSame('', $secret, "{$platform} must have no secret unless the environment sets one");
        }
    }

    public function test_env_example_names_every_platforms_secret_and_leaves_it_blank(): void
    {
        $example = file_get_contents(base_path('.env.example'));

        foreach (array_keys(config('metrics.platforms')) as $platform) {
            $name = 'ANALYTICS_INGEST_SECRET_'.strtoupper($platform);
            $this->assertMatchesRegularExpression('/^'.$name.'=$/m', $example, "{$name} must be listed, blank, in .env.example");
        }
        $this->assertMatchesRegularExpression('/^ANALYTICS_INGEST_RATE_LIMIT=120$/m', $example);
    }

    public function test_the_defaults_match_the_documented_tolerance_and_rate_limit(): void
    {
        $this->assertSame(300, config('analytics.ingest.tolerance'));
        $this->assertSame(120, config('analytics.ingest.rate_limit_per_minute'));
        $this->assertSame(65536, config('analytics.ingest.max_body_bytes'));
    }

    public function test_the_contract_page_documents_the_server_endpoint_headers_and_every_env_name(): void
    {
        $doc = file_get_contents(base_path('docs/metrics-contract.md'));

        $this->assertStringContainsString('POST /api/ingest/snapshots', $doc);
        $this->assertStringContainsString('X-InfoDot-Timestamp', $doc);
        $this->assertStringContainsString('X-InfoDot-Signature', $doc);
        $this->assertStringContainsString('HMAC-SHA256(secret, "{timestamp}.{raw body}")', $doc);
        $this->assertStringContainsString('five minutes', $doc);
        foreach (array_keys(config('metrics.platforms')) as $platform) {
            $this->assertStringContainsString('ANALYTICS_INGEST_SECRET_'.strtoupper($platform), $doc);
        }
    }

    public function test_the_worked_example_in_the_docs_is_a_real_signature(): void
    {
        $doc = file_get_contents(base_path('docs/metrics-contract.md'));
        $signature = 'sha256='.hash_hmac('sha256', self::TIMESTAMP.'.'.self::BODY, self::SECRET);

        $this->assertStringContainsString(self::SECRET, $doc);
        $this->assertStringContainsString(self::TIMESTAMP, $doc);
        $this->assertStringContainsString(self::BODY, $doc);
        $this->assertStringContainsString($signature, $doc, 'the signature in the docs does not match the documented inputs');
    }

    public function test_the_committed_openapi_spec_documents_the_unauthenticated_server_endpoint(): void
    {
        $spec = json_decode(file_get_contents(public_path('api-docs/openapi.json')), true);

        $operation = $spec['paths']['/ingest/snapshots']['post'] ?? null;
        $this->assertNotNull($operation, 'run php artisan analytics:openapi: the committed spec is stale');
        $this->assertSame([], $operation['security'], 'the server endpoint takes no bearer token');
        $this->assertEqualsCanonicalizing(['X-InfoDot-Timestamp', 'X-InfoDot-Signature'], array_column($operation['parameters'], 'name'));
    }
}
