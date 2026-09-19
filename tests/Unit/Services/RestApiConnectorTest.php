<?php

namespace Tests\Unit\Services;

use App\Services\Connectors\RestApiConnector;
use Tests\TestCase;

/**
 * RestApiConnector talks over raw curl (not Laravel's mockable Http facade)
 * and its SSRF guard blocks every loopback/private address, so a real
 * success-path network test isn't reachable offline without either a flaky
 * live HTTP call or modifying production code to make it mockable -- both
 * out of scope here. This covers everything reachable without a network:
 * the pure metadata methods and the test()/ingest() validation and SSRF
 * rejection branches, which is real, non-trivial logic in its own right.
 */
class RestApiConnectorTest extends TestCase
{
    private RestApiConnector $connector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connector = new RestApiConnector;
    }

    public function test_get_name_returns_rest_api(): void
    {
        $this->assertEquals('REST API', $this->connector->getName());
    }

    public function test_get_type_returns_rest_api(): void
    {
        $this->assertEquals('rest_api', $this->connector->getType());
    }

    public function test_get_supported_drivers_includes_rest_and_json_api(): void
    {
        $drivers = $this->connector->getSupportedDrivers();

        $this->assertContains('rest', $drivers);
        $this->assertContains('http', $drivers);
        $this->assertContains('json_api', $drivers);
    }

    public function test_test_fails_when_base_url_is_missing(): void
    {
        $result = $this->connector->test([]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('base_url is required', $result['message']);
    }

    public function test_test_fails_for_url_with_no_host(): void
    {
        $result = $this->connector->test(['base_url' => 'not-a-valid-url']);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('no host found', $result['message']);
    }

    public function test_test_blocks_loopback_addresses_as_ssrf(): void
    {
        $result = $this->connector->test(['base_url' => 'http://127.0.0.1']);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('SSRF protection', $result['message']);
    }

    public function test_test_blocks_private_ip_ranges_as_ssrf(): void
    {
        $result = $this->connector->test(['base_url' => 'http://10.0.0.5']);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('SSRF protection', $result['message']);
    }

    public function test_ingest_throws_for_loopback_base_url(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/SSRF protection/');

        $this->connector->ingest(['base_url' => 'http://localhost']);
    }

    public function test_ingest_allows_empty_base_url_without_ssrf_check(): void
    {
        // assertNotSsrf() short-circuits on an empty URL, so no exception is
        // thrown here -- the request itself will simply fail to connect,
        // which is exercised (not asserted on) rather than treated as a
        // network test.
        $result = $this->connector->ingest(['base_url' => '']);

        $this->assertEquals(0, $result['count']);
        $this->assertEmpty($result['records']);
    }
}
