<?php

namespace Tests\Unit\Services;

use App\Services\AiModelRouter;
use App\Services\AiSqlService;
use App\Services\Connectors\ConnectorRegistry;
use Tests\TestCase;

class AiSqlServiceTest extends TestCase
{
    private AiSqlService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AiSqlService(
            new AiModelRouter,
            new ConnectorRegistry,
        );
    }

    public function test_assert_safe_throws_for_drop_statement(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/DROP/');

        $method = new \ReflectionMethod(AiSqlService::class, 'assertSafe');
        $method->setAccessible(true);
        $method->invoke($this->service, 'DROP TABLE users');
    }

    public function test_assert_safe_throws_for_delete_statement(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $method = new \ReflectionMethod(AiSqlService::class, 'assertSafe');
        $method->setAccessible(true);
        $method->invoke($this->service, 'DELETE FROM users WHERE 1=1');
    }

    public function test_assert_safe_throws_for_insert_statement(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $method = new \ReflectionMethod(AiSqlService::class, 'assertSafe');
        $method->setAccessible(true);
        $method->invoke($this->service, "INSERT INTO users VALUES ('x','y')");
    }

    public function test_assert_safe_throws_when_not_starting_with_select(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/SELECT/');

        $method = new \ReflectionMethod(AiSqlService::class, 'assertSafe');
        $method->setAccessible(true);
        $method->invoke($this->service, 'SHOW TABLES');
    }

    public function test_assert_safe_allows_valid_select(): void
    {
        $method = new \ReflectionMethod(AiSqlService::class, 'assertSafe');
        $method->setAccessible(true);

        // Should not throw
        $method->invoke($this->service, 'SELECT id, name FROM users LIMIT 10');
        $this->assertTrue(true);
    }

    public function test_assert_safe_allows_complex_select(): void
    {
        $method = new \ReflectionMethod(AiSqlService::class, 'assertSafe');
        $method->setAccessible(true);

        $sql = 'SELECT u.id, u.name, COUNT(o.id) as order_count
                FROM users u
                LEFT JOIN orders o ON o.user_id = u.id
                GROUP BY u.id, u.name
                ORDER BY order_count DESC
                LIMIT 500';

        $method->invoke($this->service, $sql);
        $this->assertTrue(true);
    }
}
