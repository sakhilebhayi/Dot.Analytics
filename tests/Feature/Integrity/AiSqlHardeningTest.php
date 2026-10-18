<?php

namespace Tests\Feature\Integrity;

use App\Models\DataConnector;
use App\Services\AiModelRouter;
use App\Services\AiSqlService;
use App\Services\Connectors\ConnectorInterface;
use App\Services\Connectors\ConnectorRegistry;
use App\Services\Connectors\DatabaseConnector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsTeams;
use Tests\Support\FakeAiRouter;
use Tests\TestCase;

class AiSqlHardeningTest extends TestCase
{
    use BuildsTeams, RefreshDatabase;

    private function assertRejected(string $sql, ?string $messagePart = null): void
    {
        $service = new AiSqlService(new AiModelRouter, new ConnectorRegistry);
        $method = new \ReflectionMethod($service, 'assertSafe');
        $method->setAccessible(true);

        try {
            $method->invoke($service, $sql);
            $this->fail("Expected rejection of: {$sql}");
        } catch (\InvalidArgumentException $e) {
            if ($messagePart) {
                $this->assertStringContainsStringIgnoringCase($messagePart, $e->getMessage());
            }
            $this->assertTrue(true);
        }
    }

    private function assertAllowed(string $sql): void
    {
        $service = new AiSqlService(new AiModelRouter, new ConnectorRegistry);
        $method = new \ReflectionMethod($service, 'assertSafe');
        $method->setAccessible(true);
        $method->invoke($service, $sql);
        $this->assertTrue(true);
    }

    public function test_rejects_stacked_statements(): void
    {
        $this->assertRejected('SELECT 1; SELECT 2', 'statement');
        $this->assertRejected('SELECT 1; DROP TABLE users');
        $this->assertRejected("SELECT * FROM users;\nSELECT pg_sleep(10);");
        $this->assertRejected('SELECT 1;;');
    }

    public function test_rejects_pg_functions_including_obfuscated_ones(): void
    {
        $this->assertRejected('SELECT pg_sleep(30)', 'pg_');
        $this->assertRejected('SELECT PG_SLEEP(30)');
        $this->assertRejected("SELECT pg_read_file('/etc/passwd')");
        $this->assertRejected("SELECT pg_ls_dir('/')");
        $this->assertRejected('SELECT "pg_sleep"(30)');
        $this->assertRejected('SELECT * FROM pg_shadow');
        $this->assertRejected('SELECT pg_catalog.pg_sleep(1)');
        $this->assertRejected('SELECT U&"pg\\005fsleep"(1)');
    }

    public function test_rejects_copy_and_other_server_side_commands(): void
    {
        $this->assertRejected("COPY users TO '/tmp/x'");
        $this->assertRejected("COPY (SELECT 1) TO PROGRAM 'id'");
        $this->assertRejected("SELECT lo_import('/etc/passwd')");
        $this->assertRejected("SELECT set_config('statement_timeout','0',false)");
        $this->assertRejected("SELECT dblink('host=x','select 1')");
        $this->assertRejected('SELECT SLEEP(10)');
        $this->assertRejected("SELECT load_file('/etc/passwd')");
        $this->assertRejected("SELECT 1 INTO OUTFILE '/tmp/x'");
        $this->assertRejected('DO $$ BEGIN PERFORM 1; END $$');
    }

    public function test_rejects_comments_and_dollar_quoting_used_to_hide_payloads(): void
    {
        $this->assertRejected('SELECT 1 /* ; DROP TABLE x */');
        $this->assertRejected('SELECT 1 -- trailing');
        $this->assertRejected('SELECT $a$ ; $a$');
    }

    public function test_requires_a_single_select_or_with_select(): void
    {
        $this->assertRejected('SHOW TABLES');
        $this->assertRejected('EXPLAIN ANALYZE SELECT 1');
        $this->assertRejected('VALUES (1)');
        $this->assertRejected('WITH d AS (DELETE FROM users RETURNING *) SELECT * FROM d');
        $this->assertRejected('WITH x AS (SELECT 1)');
        $this->assertRejected('');
        $this->assertRejected('   ');
    }

    public function test_still_allows_legitimate_read_queries(): void
    {
        $this->assertAllowed('SELECT id, name FROM users LIMIT 10');
        $this->assertAllowed('SELECT id FROM users LIMIT 10;');
        $this->assertAllowed("SELECT * FROM notes WHERE body = 'a;b' LIMIT 5");
        $this->assertAllowed("SELECT 'pg_sleep' AS label");
        $this->assertAllowed('WITH recent AS (SELECT id FROM orders WHERE total > 100) SELECT COUNT(*) FROM recent');
        $this->assertAllowed('SELECT u.id, COUNT(o.id) AS n FROM users u LEFT JOIN orders o ON o.user_id = u.id GROUP BY u.id ORDER BY n DESC LIMIT 500');
        $this->assertAllowed('SELECT setting_name, reset_value FROM prefs');
    }

    public function test_api_returns_422_for_stacked_statement_from_the_model(): void
    {
        $owner = $this->ownerWithTeam();
        $connector = DataConnector::create([
            'team_id' => $owner->currentTeam->id, 'name' => 'DB', 'type' => 'database', 'driver' => 'sqlite',
            'config' => ['driver' => 'sqlite', 'database' => ':memory:'], 'status' => 'active',
        ]);
        $this->app->instance(AiModelRouter::class, new FakeAiRouter(response: 'SELECT 1; SELECT pg_sleep(60)'));
        $this->app->forgetInstance(AiSqlService::class);

        $this->withToken($owner->createToken('t')->plainTextToken)
            ->postJson('/api/v1/sql/query', [
                'question' => 'anything at all', 'connector_id' => $connector->id, 'schema' => ['tables' => [['name' => 't']]],
            ])->assertUnprocessable();
    }

    public function test_read_only_option_blocks_writes_at_the_database_layer(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'ro').'.sqlite';
        $pdo = new \PDO("sqlite:{$path}");
        $pdo->exec('CREATE TABLE t (id INTEGER)');
        $pdo->exec('INSERT INTO t VALUES (1)');
        $pdo = null;

        $connector = new DatabaseConnector;
        $base = ['driver' => 'sqlite', 'database' => $path, 'read_only' => true];

        $this->assertSame(1, $connector->ingest($base + ['query' => 'SELECT COUNT(*) AS n FROM t'])['records'][0]['n'] ?? null);

        try {
            $connector->ingest($base + ['query' => 'DELETE FROM t']);
            $this->fail('A read-only connection must refuse writes.');
        } catch (\Throwable $e) {
            $this->assertStringContainsStringIgnoringCase('readonly', str_replace(' ', '', $e->getMessage()));
        } finally {
            @unlink($path);
        }
    }

    public function test_ai_sql_service_marks_its_queries_read_only(): void
    {
        $captured = null;
        $fake = new class extends DatabaseConnector
        {
            public ?array $config = null;

            public function ingest(array $config, mixed $watermark = null): array
            {
                $this->config = $config;

                return ['records' => [], 'count' => 0, 'next_watermark' => null];
            }
        };
        $registry = new class($fake) extends ConnectorRegistry
        {
            public function __construct(private DatabaseConnector $db) {}

            public function forDriver(string $driver): ?ConnectorInterface
            {
                return $this->db;
            }
        };
        $service = new AiSqlService(new FakeAiRouter(response: 'SELECT 1'), $registry);
        $connector = new DataConnector(['driver' => 'sqlite', 'config' => ['driver' => 'sqlite', 'database' => ':memory:']]);

        $service->query('anything at all', $connector, 1, ['tables' => [['name' => 't']]]);

        $this->assertTrue($fake->config['read_only'] ?? false);
    }
}
