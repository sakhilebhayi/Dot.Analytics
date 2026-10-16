<?php

namespace Tests\Unit\Services;

use App\Services\Connectors\DatabaseConnector;
use Tests\TestCase;

class DatabaseConnectorTest extends TestCase
{
    private DatabaseConnector $connector;

    private string $dbPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connector = new DatabaseConnector;
        $this->dbPath = tempnam(sys_get_temp_dir(), 'db_connector_test_').'.sqlite';

        $pdo = new \PDO("sqlite:{$this->dbPath}");
        $pdo->exec('CREATE TABLE widgets (id INTEGER PRIMARY KEY, name TEXT, updated_at INTEGER)');
        $pdo->exec("INSERT INTO widgets (id, name, updated_at) VALUES (1, 'Alpha', 100)");
        $pdo->exec("INSERT INTO widgets (id, name, updated_at) VALUES (2, 'Beta', 200)");
        $pdo->exec("INSERT INTO widgets (id, name, updated_at) VALUES (3, 'Gamma', 300)");
    }

    protected function tearDown(): void
    {
        @unlink($this->dbPath);
        parent::tearDown();
    }

    private function config(array $overrides = []): array
    {
        return array_merge([
            'driver' => 'sqlite',
            'database' => $this->dbPath,
            'table' => 'widgets',
        ], $overrides);
    }

    public function test_get_name_returns_database(): void
    {
        $this->assertEquals('Database', $this->connector->getName());
    }

    public function test_get_type_returns_database(): void
    {
        $this->assertEquals('database', $this->connector->getType());
    }

    public function test_get_supported_drivers_includes_common_engines(): void
    {
        $drivers = $this->connector->getSupportedDrivers();

        $this->assertContains('postgres', $drivers);
        $this->assertContains('mysql', $drivers);
        $this->assertContains('sqlite', $drivers);
    }

    public function test_test_succeeds_for_a_reachable_database(): void
    {
        $result = $this->connector->test($this->config());

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('latency_ms', $result);
    }

    public function test_test_fails_for_an_unreachable_database(): void
    {
        $result = $this->connector->test([
            'driver' => 'sqlite',
            'database' => '/nonexistent/dir/that/does/not/exist.sqlite',
        ]);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('message', $result);
    }

    public function test_ingest_returns_all_rows_from_the_configured_table(): void
    {
        $result = $this->connector->ingest($this->config());

        $this->assertEquals(3, $result['count']);
        $this->assertCount(3, $result['records']);
    }

    public function test_ingest_uses_a_custom_query_when_provided(): void
    {
        $result = $this->connector->ingest($this->config([
            'query' => "SELECT * FROM widgets WHERE name = 'Beta'",
        ]));

        $this->assertEquals(1, $result['count']);
        $this->assertEquals('Beta', $result['records'][0]['name']);
    }

    public function test_ingest_applies_watermark_when_provided(): void
    {
        $result = $this->connector->ingest($this->config([
            'watermark_column' => 'updated_at',
        ]), 100);

        $this->assertEquals(2, $result['count']);
        $this->assertEquals(300, $result['next_watermark']);
    }

    public function test_ingest_without_watermark_returns_null_next_watermark(): void
    {
        $result = $this->connector->ingest($this->config());

        $this->assertNull($result['next_watermark']);
    }

    public function test_get_schema_lists_table_names(): void
    {
        $schema = $this->connector->getSchema($this->config());

        $this->assertArrayHasKey('tables', $schema);
        $tableNames = array_column($schema['tables'], 'name');
        $this->assertContains('widgets', $tableNames);
    }
}
