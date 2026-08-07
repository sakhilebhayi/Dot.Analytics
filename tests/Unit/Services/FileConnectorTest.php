<?php

namespace Tests\Unit\Services;

use App\Services\Connectors\FileConnector;
use Tests\TestCase;

class FileConnectorTest extends TestCase
{
    private FileConnector $connector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connector = new FileConnector;
    }

    public function test_get_type_returns_file(): void
    {
        $this->assertEquals('file', $this->connector->getType());
    }

    public function test_get_supported_drivers_includes_csv_and_json(): void
    {
        $drivers = $this->connector->getSupportedDrivers();
        $this->assertContains('csv', $drivers);
        $this->assertContains('json', $drivers);
        $this->assertContains('excel', $drivers);
    }

    public function test_ingest_parses_csv_content_with_headers(): void
    {
        $csv = "name,age,city\nAlice,30,Cape Town\nBob,25,Johannesburg";

        $result = $this->connector->ingest([
            'driver' => 'csv',
            'content' => $csv,
        ]);

        $this->assertEquals(2, $result['count']);
        $this->assertEquals('Alice', $result['records'][0]['name']);
        $this->assertEquals('30', $result['records'][0]['age']);
        $this->assertEquals('Johannesburg', $result['records'][1]['city']);
    }

    public function test_ingest_parses_csv_without_headers(): void
    {
        $csv = "Alice,30\nBob,25";

        $result = $this->connector->ingest([
            'driver' => 'csv',
            'content' => $csv,
            'has_header' => false,
        ]);

        $this->assertEquals(2, $result['count']);
        $this->assertEquals(['Alice', '30'], $result['records'][0]);
    }

    public function test_ingest_parses_json_array(): void
    {
        $json = json_encode([
            ['id' => 1, 'value' => 100],
            ['id' => 2, 'value' => 200],
        ]);

        $result = $this->connector->ingest([
            'driver' => 'json',
            'content' => $json,
        ]);

        $this->assertEquals(2, $result['count']);
        $this->assertEquals(1, $result['records'][0]['id']);
    }

    public function test_ingest_parses_json_with_records_path(): void
    {
        $json = json_encode(['data' => ['items' => [['id' => 1], ['id' => 2]]]]);

        $result = $this->connector->ingest([
            'driver' => 'json',
            'content' => $json,
            'records_path' => 'data.items',
        ]);

        $this->assertEquals(2, $result['count']);
    }

    public function test_ingest_applies_watermark_offset(): void
    {
        $csv = "id,value\n1,a\n2,b\n3,c\n4,d";

        $result = $this->connector->ingest([
            'driver' => 'csv',
            'content' => $csv,
        ], 2); // Skip first 2 records

        $this->assertEquals(2, $result['count']);
        $this->assertEquals('3', $result['records'][0]['id']);
    }

    public function test_ingest_returns_empty_for_missing_content(): void
    {
        $result = $this->connector->ingest(['driver' => 'csv']);

        $this->assertEquals(0, $result['count']);
        $this->assertEmpty($result['records']);
    }

    public function test_test_returns_false_for_nonexistent_file(): void
    {
        $result = $this->connector->test(['file_path' => '/nonexistent/path.csv']);

        $this->assertFalse($result['success']);
    }

    public function test_test_returns_false_when_no_path_provided(): void
    {
        $result = $this->connector->test([]);

        $this->assertFalse($result['success']);
    }

    public function test_get_schema_returns_field_names(): void
    {
        $csv = "product,price,qty\nApple,1.5,100\nBanana,0.8,200";

        $schema = $this->connector->getSchema([
            'driver' => 'csv',
            'content' => $csv,
        ]);

        $this->assertArrayHasKey('tables', $schema);
        $this->assertArrayHasKey('fields', $schema);
        $fieldNames = array_column($schema['fields'], 'name');
        $this->assertContains('product', $fieldNames);
        $this->assertContains('price', $fieldNames);
    }

    public function test_ingest_parses_tsv_content(): void
    {
        $tsv = "name\tscore\nAlice\t95\nBob\t87";

        $result = $this->connector->ingest([
            'driver' => 'tsv',
            'content' => $tsv,
        ]);

        $this->assertEquals(2, $result['count']);
        $this->assertEquals('Alice', $result['records'][0]['name']);
        $this->assertEquals('95', $result['records'][0]['score']);
    }
}
