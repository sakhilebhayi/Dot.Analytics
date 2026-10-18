<?php

namespace App\Services\Connectors;

/**
 * Database connector via PDO.
 *
 * Connects to external relational databases: PostgreSQL, MySQL,
 * SQL Server, Oracle, and SQLite. Executes user-defined queries
 * and maps results into the canonical analytics payload format.
 */
class DatabaseConnector implements ConnectorInterface
{
    public function getName(): string
    {
        return 'Database';
    }

    public function getType(): string
    {
        return 'database';
    }

    public function getSupportedDrivers(): array
    {
        return ['postgres', 'pgsql', 'mysql', 'sqlserver', 'mssql', 'oracle', 'sqlite'];
    }

    public function test(array $config): array
    {
        $start = microtime(true);

        try {
            $pdo = $this->connect($config);
            $pdo->query('SELECT 1');
            $latency = (int) ((microtime(true) - $start) * 1000);

            return ['success' => true, 'message' => 'Connection successful.', 'latency_ms' => $latency];
        } catch (\PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function ingest(array $config, mixed $watermark = null): array
    {
        $pdo = $this->connect($config);
        $query = $config['query'] ?? 'SELECT * FROM '.($config['table'] ?? 'data');

        $inTransaction = ! empty($config['read_only']) && $this->applyReadOnlyGuards($pdo, $config);

        try {
            if ($watermark && ! empty($config['watermark_column'])) {
                $query .= " WHERE {$config['watermark_column']} > :watermark ORDER BY {$config['watermark_column']}";
                $stmt = $pdo->prepare($query);
                $stmt->execute(['watermark' => $watermark]);
            } else {
                $stmt = $pdo->query($query);
            }

            $records = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } finally {
            if ($inTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }
        $nextMark = ! empty($config['watermark_column']) && $records
            ? end($records)[$config['watermark_column']] ?? null
            : null;

        return [
            'records' => $records,
            'count' => count($records),
            'next_watermark' => $nextMark,
        ];
    }

    public function getSchema(array $config): array
    {
        $pdo = $this->connect($config);
        $driver = $config['driver'] ?? 'postgres';

        // Get table list
        $tableQuery = match (true) {
            in_array($driver, ['postgres', 'pgsql']) => "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'",
            in_array($driver, ['mysql']) => 'SHOW TABLES',
            default => "SELECT name FROM sqlite_master WHERE type='table'",
        };

        $tables = $pdo->query($tableQuery)->fetchAll(\PDO::FETCH_COLUMN);

        return [
            'tables' => array_map(fn ($t) => ['name' => $t], $tables),
            'fields' => [],
            'sample' => [],
        ];
    }

    /**
     * Make the connection refuse writes and bound execution time, for callers that
     * run untrusted (AI-generated) SQL. Returns true when a transaction was opened
     * that the caller must roll back.
     *
     * Postgres: read-only transaction + statement_timeout. MySQL: read-only session +
     * max_execution_time. SQLite: query_only. SQL Server has no equivalent switch;
     * there the connector's DB account must be read-only.
     */
    private function applyReadOnlyGuards(\PDO $pdo, array $config): bool
    {
        $driver = strtolower($config['driver'] ?? 'postgres');
        $timeoutMs = (int) ($config['statement_timeout_ms'] ?? 15000);

        switch ($driver) {
            case 'sqlite':
                $pdo->exec('PRAGMA query_only = ON');

                return false;

            case 'mysql':
                $pdo->exec('SET SESSION TRANSACTION READ ONLY');
                try {
                    $pdo->exec("SET SESSION MAX_EXECUTION_TIME = {$timeoutMs}");
                } catch (\PDOException) {
                    // MariaDB names it max_statement_time (seconds); best effort.
                    try {
                        $pdo->exec('SET SESSION max_statement_time = '.max(1, intdiv($timeoutMs, 1000)));
                    } catch (\PDOException) {
                    }
                }

                return false;

            case 'sqlserver':
            case 'mssql':
            case 'oracle':
                return false;

            default: // postgres / pgsql
                $pdo->beginTransaction();
                $pdo->exec('SET TRANSACTION READ ONLY');
                $pdo->exec("SET LOCAL statement_timeout = {$timeoutMs}");

                return true;
        }
    }

    /** @throws \PDOException */
    private function connect(array $config): \PDO
    {
        $driver = strtolower($config['driver'] ?? 'postgres');
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 5432;
        $dbname = $config['database'] ?? '';
        $user = $config['username'] ?? '';
        $pass = $config['password'] ?? '';

        $dsn = match ($driver) {
            'mysql' => "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4",
            'sqlserver', 'mssql' => "sqlsrv:Server={$host},{$port};Database={$dbname}",
            'sqlite' => "sqlite:{$dbname}",
            default => "pgsql:host={$host};port={$port};dbname={$dbname}",
        };

        return new \PDO($dsn, $user, $pass, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_TIMEOUT => 10,
        ]);
    }
}
