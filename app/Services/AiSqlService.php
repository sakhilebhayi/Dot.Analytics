<?php

namespace App\Services;

use App\Models\DataConnector;
use App\Services\Connectors\ConnectorRegistry;

/**
 * AI SQL Service — Natural Language to SQL
 *
 * Accepts a natural-language question, generates a safe read-only SQL query
 * using the configured AI model, executes it against the specified external
 * database connector, and returns structured results with the generated SQL
 * for transparency and auditability.
 *
 * Security measures (defence in depth; the connector's DB account should ALSO be
 * read-only, because no SQL filter is a complete sandbox):
 *  - Exactly ONE statement: SELECT, or WITH ... SELECT. Stacked statements are
 *    rejected (a single trailing ';' is tolerated and stripped).
 *  - Comments, dollar-quoting, backslashes and Unicode-escaped identifiers are
 *    rejected so payloads cannot be hidden from the checks below.
 *  - Write/DDL/admin keywords (DROP, INSERT, COPY, DO, ...) and dangerous
 *    functions (pg_*, lo_*, set_config, dblink*, sleep, load_file, ...) are rejected.
 *  - The query runs on a connection marked read_only (read-only transaction and
 *    statement timeout where the driver supports it).
 *  - Results are limited to 500 rows.
 */
class AiSqlService
{
    private const FORBIDDEN_KEYWORDS = [
        'DROP', 'DELETE', 'INSERT', 'UPDATE', 'TRUNCATE', 'ALTER', 'CREATE',
        'REPLACE', 'EXEC', 'EXECUTE', 'GRANT', 'REVOKE', 'CALL', 'MERGE',
        'INTO', 'SET', 'LOCK', 'UNLOCK',
        'COPY', 'DO', 'LISTEN', 'NOTIFY', 'PREPARE', 'DECLARE', 'VACUUM', 'REINDEX',
        'CLUSTER', 'LOAD', 'IMPORT', 'HANDLER', 'SHUTDOWN', 'KILL', 'ATTACH', 'DETACH', 'PRAGMA',
    ];

    /** Functions / identifier families that read files, sleep, shell out or change session state. */
    private const FORBIDDEN_FUNCTION_PATTERNS = [
        '/\bpg_\w*/i' => 'pg_*',
        '/\blo_\w+/i' => 'lo_*',
        '/\bdblink\w*/i' => 'dblink',
        '/\bset_config\b/i' => 'set_config',
        '/\bcurrent_setting\b/i' => 'current_setting',
        '/\b(?:sleep|benchmark|waitfor|load_file|readfile|xp_\w+|sys_exec|sys_eval)\b/i' => 'server-side function',
    ];

    public function __construct(
        private readonly AiModelRouter $aiRouter,
        private readonly ConnectorRegistry $connectorRegistry,
    ) {}

    /**
     * Generate SQL from a natural-language question and execute it.
     *
     * @return array{sql: string, results: array, row_count: int, columns: array, explanation: string}
     */
    public function query(
        string $question,
        DataConnector $connector,
        int $teamId,
        ?array $schemaSummary = null,
    ): array {
        $schema = $schemaSummary ?? $this->getSchema($connector);

        $sql = $this->generateSql($question, $schema, $teamId);

        $this->assertSafe($sql);

        $results = $this->executeSql($sql, $connector);

        return [
            'sql' => $sql,
            'results' => $results,
            'row_count' => count($results),
            'columns' => $results ? array_keys($results[0]) : [],
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
        // read_only is merged LAST so a connector's stored config can never switch it off.
        $config = array_merge($connector->config, ['query' => $sql, 'read_only' => true]);
        $result = $dbConnector->ingest($config);

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
     * Allow exactly one read-only SELECT (or WITH ... SELECT) statement.
     *
     * @throws \InvalidArgumentException
     */
    private function assertSafe(string $sql): void
    {
        $sql = trim($sql);
        if ($sql === '') {
            throw new \InvalidArgumentException('Generated SQL is empty. Only SELECT queries are permitted.');
        }

        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $sql) || str_contains($sql, '\\')) {
            throw new \InvalidArgumentException('Control characters and backslash escapes are not permitted in generated SQL.');
        }

        // Blank out the CONTENT of single-quoted literals so ';' / keywords inside
        // a string are not mistaken for code (and vice versa).
        $code = preg_replace("/'(?:[^']|'')*'/s", '__LIT__', $sql);

        if (str_contains($code, "'")) {
            throw new \InvalidArgumentException('Unterminated string literal in generated SQL.');
        }

        if (preg_match('#--|/\*|\*/|\#|\$[A-Za-z_0-9]*\$|\bU&#i', $code)) {
            throw new \InvalidArgumentException('Comments, dollar-quoted strings and Unicode escapes are not permitted in generated SQL.');
        }

        // One statement only: tolerate a single trailing semicolon, nothing else.
        $code = rtrim($code);
        if (str_ends_with($code, ';')) {
            $code = rtrim(substr($code, 0, -1));
        }
        if (str_contains($code, ';')) {
            throw new \InvalidArgumentException('Multiple statements are not permitted. Provide a single SELECT statement.');
        }

        $upper = strtoupper($code);

        foreach (self::FORBIDDEN_KEYWORDS as $keyword) {
            if (preg_match('/\b'.preg_quote($keyword, '/').'\b/', $upper)) {
                throw new \InvalidArgumentException(
                    "Generated SQL contains forbidden keyword '{$keyword}'. Only SELECT queries are allowed."
                );
            }
        }

        foreach (self::FORBIDDEN_FUNCTION_PATTERNS as $pattern => $label) {
            if (preg_match($pattern, $code)) {
                throw new \InvalidArgumentException(
                    "Generated SQL uses a forbidden function or object ({$label}). Only plain SELECT queries are allowed."
                );
            }
        }

        if (! preg_match('/^\s*(?:SELECT|WITH)\b/i', $code)) {
            throw new \InvalidArgumentException('Only SELECT queries are permitted.');
        }

        if (preg_match('/^\s*WITH\b/i', $code) && ! preg_match('/\bSELECT\b/i', $this->topLevelOnly($code))) {
            throw new \InvalidArgumentException('A WITH query must end in a SELECT. Only SELECT queries are permitted.');
        }
    }

    /**
     * The statement with everything inside parentheses blanked out, so that the
     * main query of a WITH can be told apart from SELECTs inside its CTEs.
     */
    private function topLevelOnly(string $code): string
    {
        $depth = 0;
        $out = '';

        foreach (str_split($code) as $char) {
            if ($char === '(') {
                $depth++;
                $out .= '(';
            } elseif ($char === ')') {
                $depth--;
                if ($depth < 0) {
                    throw new \InvalidArgumentException('Unbalanced parentheses in generated SQL.');
                }
                $out .= ')';
            } elseif ($depth === 0) {
                $out .= $char;
            }
        }

        if ($depth !== 0) {
            throw new \InvalidArgumentException('Unbalanced parentheses in generated SQL.');
        }

        return $out;
    }
}
