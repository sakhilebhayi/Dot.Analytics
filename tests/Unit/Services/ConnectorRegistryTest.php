<?php

namespace Tests\Unit\Services;

use App\Services\Connectors\ConnectorRegistry;
use App\Services\Connectors\DatabaseConnector;
use App\Services\Connectors\FileConnector;
use App\Services\Connectors\RestApiConnector;
use Tests\TestCase;

class ConnectorRegistryTest extends TestCase
{
    private ConnectorRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new ConnectorRegistry;
        $this->registry->register(new FileConnector);
        $this->registry->register(new RestApiConnector);
        $this->registry->register(new DatabaseConnector);
    }

    public function test_get_returns_registered_connector(): void
    {
        $connector = $this->registry->get('file');
        $this->assertInstanceOf(FileConnector::class, $connector);
    }

    public function test_get_returns_null_for_unknown_type(): void
    {
        $this->assertNull($this->registry->get('unknown_type'));
    }

    public function test_for_driver_finds_connector_by_driver(): void
    {
        $connector = $this->registry->forDriver('csv');
        $this->assertInstanceOf(FileConnector::class, $connector);
    }

    public function test_for_driver_finds_database_connector(): void
    {
        $connector = $this->registry->forDriver('postgres');
        $this->assertInstanceOf(DatabaseConnector::class, $connector);
    }

    public function test_for_driver_finds_rest_connector(): void
    {
        $connector = $this->registry->forDriver('rest');
        $this->assertInstanceOf(RestApiConnector::class, $connector);
    }

    public function test_for_driver_returns_null_for_unknown_driver(): void
    {
        $this->assertNull($this->registry->forDriver('unknown_driver_xyz'));
    }

    public function test_all_returns_all_registered_connectors(): void
    {
        $all = $this->registry->all();
        $this->assertCount(3, $all);
    }

    public function test_types_returns_all_registered_type_keys(): void
    {
        $types = $this->registry->types();
        $this->assertContains('file', $types);
        $this->assertContains('rest_api', $types);
        $this->assertContains('database', $types);
    }

    public function test_register_replaces_existing_type(): void
    {
        $newFile = new FileConnector;
        $this->registry->register($newFile);

        $this->assertCount(3, $this->registry->all()); // Still 3, not 4
    }
}
