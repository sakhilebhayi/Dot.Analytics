<?php

namespace Tests\Unit\Security;

use App\Services\Connectors\RestApiConnector;
use Tests\TestCase;

/**
 * Security tests for SSRF protection in the RestApiConnector.
 *
 * Every test verifies that the connector blocks requests to private,
 * loopback, and reserved IP ranges — mitigating Server-Side Request
 * Forgery (OWASP A10).
 */
class SsrfProtectionTest extends TestCase
{
    private RestApiConnector $connector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connector = new RestApiConnector();
    }

    public function test_test_blocks_localhost(): void
    {
        $result = $this->connector->test(['base_url' => 'http://localhost/admin']);

        $this->assertFalse($result['success']);
        $this->assertStringContainsStringIgnoringCase('SSRF', $result['message']);
    }

    public function test_test_blocks_loopback_ip(): void
    {
        $result = $this->connector->test(['base_url' => 'http://127.0.0.1/']);

        $this->assertFalse($result['success']);
        $this->assertStringContainsStringIgnoringCase('SSRF', $result['message']);
    }

    public function test_test_blocks_private_class_a(): void
    {
        $result = $this->connector->test(['base_url' => 'http://10.0.0.1/api']);

        $this->assertFalse($result['success']);
    }

    public function test_test_blocks_private_class_b(): void
    {
        $result = $this->connector->test(['base_url' => 'http://172.16.0.1/api']);

        $this->assertFalse($result['success']);
    }

    public function test_test_blocks_private_class_c(): void
    {
        $result = $this->connector->test(['base_url' => 'http://192.168.1.1/api']);

        $this->assertFalse($result['success']);
    }

    public function test_test_blocks_empty_url(): void
    {
        $result = $this->connector->test(['base_url' => '']);
        $this->assertFalse($result['success']);
    }

    public function test_test_blocks_missing_url(): void
    {
        $result = $this->connector->test([]);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('base_url', $result['message']);
    }

    public function test_ingest_blocks_private_address(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/SSRF/');

        $this->connector->ingest(['base_url' => 'http://192.168.1.100/data', 'driver' => 'rest']);
    }
}
