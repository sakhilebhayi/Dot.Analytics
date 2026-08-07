<?php

namespace Tests\Unit\Services;

use App\Services\Connectors\ConnectorRegistry;
use App\Services\PipelineExecutionService;
use Tests\TestCase;

class PipelineExecutionServiceTest extends TestCase
{
    private PipelineExecutionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PipelineExecutionService(new ConnectorRegistry);
    }

    // ─── Safe arithmetic evaluator ───────────────────────────────────────────

    private function evalComputed(string $expr, array $record): mixed
    {
        $method = new \ReflectionMethod(PipelineExecutionService::class, 'evalComputed');
        $method->setAccessible(true);

        return $method->invoke($this->service, $expr, $record);
    }

    public function test_eval_computed_handles_addition(): void
    {
        $this->assertEquals(5.0, $this->evalComputed('2 + 3', []));
    }

    public function test_eval_computed_handles_subtraction(): void
    {
        $this->assertEquals(1.0, $this->evalComputed('3 - 2', []));
    }

    public function test_eval_computed_handles_multiplication(): void
    {
        $this->assertEquals(12.0, $this->evalComputed('3 * 4', []));
    }

    public function test_eval_computed_handles_division(): void
    {
        $this->assertEquals(2.5, $this->evalComputed('5 / 2', []));
    }

    public function test_eval_computed_handles_division_by_zero_safely(): void
    {
        $this->assertEquals(0.0, $this->evalComputed('10 / 0', []));
    }

    public function test_eval_computed_handles_parentheses(): void
    {
        $this->assertEquals(10.0, $this->evalComputed('(2 + 3) * 2', []));
    }

    public function test_eval_computed_handles_nested_parentheses(): void
    {
        $this->assertEquals(12.0, $this->evalComputed('2 * (3 + (4 - 1))', []));
    }

    public function test_eval_computed_handles_decimals(): void
    {
        $this->assertEqualsWithDelta(0.3, $this->evalComputed('0.1 + 0.2', []), 0.001);
    }

    public function test_eval_computed_interpolates_field_values(): void
    {
        $result = $this->evalComputed('{price} * {qty}', ['price' => 10, 'qty' => 5]);
        $this->assertEquals(50.0, $result);
    }

    public function test_eval_computed_uses_zero_for_missing_fields(): void
    {
        $result = $this->evalComputed('{missing} + 5', []);
        $this->assertEquals(5.0, $result);
    }

    public function test_eval_computed_returns_string_for_non_arithmetic(): void
    {
        $result = $this->evalComputed('Hello {name}', ['name' => 'World']);
        $this->assertEquals('Hello World', $result);
    }

    // ─── Transform stage ─────────────────────────────────────────────────────

    private function transform(array $records, array $config): array
    {
        $method = new \ReflectionMethod(PipelineExecutionService::class, 'transform');
        $method->setAccessible(true);

        return $method->invoke($this->service, $records, $config);
    }

    public function test_transform_renames_fields(): void
    {
        $records = [['old_name' => 'Alice']];
        $result = $this->transform($records, ['field_map' => ['old_name' => 'name']]);

        $this->assertArrayHasKey('name', $result[0]);
        $this->assertArrayNotHasKey('old_name', $result[0]);
        $this->assertEquals('Alice', $result[0]['name']);
    }

    public function test_transform_casts_integer_type(): void
    {
        $records = [['score' => '42']];
        $result = $this->transform($records, ['type_cast' => ['score' => 'int']]);

        $this->assertIsInt($result[0]['score']);
        $this->assertEquals(42, $result[0]['score']);
    }

    public function test_transform_casts_float_type(): void
    {
        $records = [['price' => '9.99']];
        $result = $this->transform($records, ['type_cast' => ['price' => 'float']]);

        $this->assertIsFloat($result[0]['price']);
    }

    public function test_transform_drops_fields(): void
    {
        $records = [['keep' => 'yes', 'drop' => 'no']];
        $result = $this->transform($records, ['drop_fields' => ['drop']]);

        $this->assertArrayHasKey('keep', $result[0]);
        $this->assertArrayNotHasKey('drop', $result[0]);
    }

    public function test_transform_filters_records(): void
    {
        $records = [
            ['status' => 'active'],
            ['status' => 'inactive'],
            ['status' => 'active'],
        ];
        $result = $this->transform($records, [
            'filter' => ['field' => 'status', 'operator' => '=', 'value' => 'active'],
        ]);

        $this->assertCount(2, $result);
    }

    public function test_transform_adds_computed_field(): void
    {
        $records = [['price' => 10, 'qty' => 3]];
        $result = $this->transform($records, [
            'computed_fields' => ['total' => '{price} * {qty}'],
        ]);

        $this->assertEquals(30.0, $result[0]['total']);
    }

    // ─── Quality assessment ──────────────────────────────────────────────────

    public function test_assess_quality_returns_completeness_100_for_full_data(): void
    {
        $method = new \ReflectionMethod(PipelineExecutionService::class, 'assessQuality');
        $method->setAccessible(true);

        $raw = [['a' => 1, 'b' => 2]];
        $result = $method->invoke($this->service, $raw, $raw);

        $this->assertEquals(100.0, $result['completeness_pct']);
        $this->assertEquals(0, $result['rejected_records']);
    }

    public function test_assess_quality_counts_rejected_records(): void
    {
        $method = new \ReflectionMethod(PipelineExecutionService::class, 'assessQuality');
        $method->setAccessible(true);

        $raw = [['a' => 1], ['b' => 2], ['c' => 3]];
        $transformed = [['a' => 1]];
        $result = $method->invoke($this->service, $raw, $transformed);

        $this->assertEquals(2, $result['rejected_records']);
    }
}
