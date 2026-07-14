<?php

namespace App\Services;

use App\Models\DataConnector;
use App\Services\Connectors\ConnectorRegistry;
use Illuminate\Support\Facades\Log;

/**
 * AI SQL Service — Natural Language to SQL
 *
 * Accepts a natural-language question, generates a safe read-only SQL query
 * using the configured AI model, executes it against the specified external
 * database connector, and returns structured results with the generated SQL
 * for transparency and auditability.
 *
 * Security measures:
 *  - Only SELECT statements are permitted
 *  - All dangerous keywords (DROP, INSERT, UPDATE, DELETE, TRUNCATE, EXEC, etc.)
 *    are rejected before execution
 *  - Results are limited to 500 rows
 *  - Execution time is bounded by PDO timeout
 */
class AiSqlService
{
    private const FORBIDDEN_KEYWORDS = [
        'DROP', 'DELETE', 'INSERT', 'UPDATE', 'TRUNCATE', 'ALTER', 'CREATE',
        'REPLACE', 'EXEC', 'EXECUTE', 'GRANT', 'REVOKE', 'CALL', 'MERGE',
        'INTO', 'SET', 'LOCK', 'UNLOCK',
    ];

    public function __construct(
        private readonly AiModelRouter     $aiRouter,
        private readonly ConnectorRegistry $connectorRegistry,
    ) {}

    /**
     * Generate SQL from a natural-language question and execute it.
     *
     * @return array{sql: string, results: array, row_count: int, columns: array, explanation: string}
     */
    public function query(
        string       $question,
        DataConnector $connector,
        int           $teamId,
        ?array        $schemaSummary = null,
    ): array {
        $schema = $schemaSummary ?? $this->getSchema($connector);

        $sql = $this->generateSql($question, $schema, $teamId);

        $this->assertSafe($sql);

        $results = $this->executeSql($sql, $connector);

        return [
            'sql'         => $sql,
            'results'     => $results,
            'row_count'   => count($results),
            'columns'     => $results ? array_keys($results[0]) : [],
            'explanation' => $this->explainSql($sql, $question, $teamId),
        ];
    }

    /**
     * Generate SQL from natural language using the AI model.
     */
    public function generateSql(string $question, array $schema, int $teamId): string
    {
        $schemaDesc = $this->describeSchema($schema);

        $prompt = <<<PROMPT
You are a SQL expert generating read-only queries for a business intelligence platform.

Database schema:
{$schemaDesc}

Question: {$question}

Rules:
- Return ONLY the SQL query, no markdown, no explanation
- Use only SELECT statements
- Limit results to 500 rows using LIMIT 500
- Use proper table aliases for readability
- Prefer readable column aliases in results
PROMPT;

        $sql = trim($this->aiRouter->complete($prompt, 'sql', $teamId));

        // Strip markdown code blocks if the model included them
        $sql = preg_replace('/^```(?:sql)?\n?/i', '', $sql);
        $sql = preg_replace('/\n?```$/', '', $sql);

        return trim($sql);
    }

    /**
     * Execute a SQL query against a connector's external database.
     */
    private function executeSql(string $sql, DataConnector $connector): array
    {
        $dbConnector = $this->connectorRegistry->forDriver($connector->driver);
        if (! $dbConnector) {
            throw new \RuntimeException("No database connector registered for driver '{$connector->driver}'.");
        }

        // Use a custom ingest with the SQL as the query
        $config         = array_merge($connector->config, ['query' => $sql]);
        $result         = $dbConnector->ingest($config);

        return array_slice($result['records'], 0, 500); // Hard cap
    }

    /**
     * Generate a plain-English explanation of what the SQL does.
     */
    private function explainSql(string $sql, string $question, int $teamId): string
    {
        $prompt = "Explain in one sentence what this SQL query returns in response to: \"{$question}\"\n\nSQL:\n{$sql}";
        return $this->aiRouter->complete($prompt, 'summarisation', $teamId, null, 200);
    }

    private function getSchema(DataConnector $connector): array
    {
        $dbConnector = $this->connectorRegistry->forDriver($connector->driver);
        return $dbConnector?->getSchema($connector->config) ?? [];
    }

    private function describeSchema(array $schema): string
    {
        if (empty($schema['tables'])) {
            return 'No schema available.';
        }

        $lines = [];
        foreach ($schema['tables'] as $table) {
            $lines[] = "Table: {$table['name']}";
        }
        if (! empty($schema['fields'])) {
            foreach ($schema['fields'] as $field) {
                $lines[] = "  Column: {$field['name']} ({$field['type']})";
            }
        }
        return implode("\n", $lines);
    }

    /**
     * Reject any SQL that contains write or DDL keywords.
     *
     * @throws \InvalidArgumentException
     */
    private function assertSafe(string $sql): void
    {
        $upper = strtoupper($sql);

        foreach (self::FORBIDDEN_KEYWORDS as $keyword) {
            if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/', $upper)) {
                throw new \InvalidArgumentException(
                    "Generated SQL contains forbidden keyword '{$keyword}'. Only SELECT queries are allowed."
                );
            }
        }

        if (! preg_match('/^\s*SELECT\b/i', $sql)) {
            throw new \InvalidArgumentException('Only SELECT queries are permitted.');
        }
    }
}
