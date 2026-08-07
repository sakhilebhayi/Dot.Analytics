<?php

namespace App\Services\Connectors;

use App\Models\DataConnector;

/**
 * Central registry of all available external data connectors.
 *
 * New connector types can be registered by binding them to this
 * registry in a service provider — no code changes required.
 */
class ConnectorRegistry
{
    /** @var ConnectorInterface[] */
    private array $connectors = [];

    public function register(ConnectorInterface $connector): self
    {
        $this->connectors[$connector->getType()] = $connector;

        return $this;
    }

    public function get(string $type): ?ConnectorInterface
    {
        return $this->connectors[$type] ?? null;
    }

    public function forDriver(string $driver): ?ConnectorInterface
    {
        foreach ($this->connectors as $connector) {
            if (in_array($driver, $connector->getSupportedDrivers(), true)) {
                return $connector;
            }
        }

        return null;
    }

    /** @return ConnectorInterface[] */
    public function all(): array
    {
        return $this->connectors;
    }

    public function types(): array
    {
        return array_keys($this->connectors);
    }

    /**
     * Test a DataConnector model's connection using its configured connector.
     */
    public function testModel(DataConnector $model): array
    {
        $connector = $this->get($model->type) ?? $this->forDriver($model->driver);

        if (! $connector) {
            return ['success' => false, 'message' => "No connector registered for type '{$model->type}' / driver '{$model->driver}'."];
        }

        return $connector->test($model->config);
    }

    /**
     * Ingest data from a DataConnector model.
     */
    public function ingestModel(DataConnector $model, mixed $watermark = null): array
    {
        $connector = $this->get($model->type) ?? $this->forDriver($model->driver);

        if (! $connector) {
            return ['records' => [], 'count' => 0, 'next_watermark' => null];
        }

        return $connector->ingest($model->config, $watermark);
    }
}
