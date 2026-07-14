<?php

namespace App\Services\Connectors;

/**
 * Contract that every external data connector must implement.
 *
 * Connectors translate between external data systems and the
 * Dot.Analytics canonical data model.
 */
interface ConnectorInterface
{
    /**
     * Test the connection using the provided config.
     * Returns ['success' => true/false, 'message' => '...', 'latency_ms' => int]
     */
    public function test(array $config): array;

    /**
     * Ingest data from the external source.
     * Returns ['records' => [...], 'count' => int, 'next_watermark' => mixed]
     */
    public function ingest(array $config, mixed $watermark = null): array;

    /**
     * Discover the schema of the external source.
     * Returns ['tables' => [...], 'fields' => [...], 'sample' => [...]]
     */
    public function getSchema(array $config): array;

    /**
     * Human-readable name for this connector type.
     */
    public function getName(): string;

    /**
     * Connector type key (e.g. 'rest_api', 'database', 'file').
     */
    public function getType(): string;

    /**
     * List of driver strings this connector supports.
     * E.g. ['postgres', 'mysql', 'sqlserver'] or ['csv', 'json', 'excel']
     */
    public function getSupportedDrivers(): array;
}
