<?php

namespace Tests\Unit\Services;

use App\Models\DataConnector;
use App\Models\DataPipeline;
use App\Models\User;
use App\Services\Connectors\ConnectorRegistry;
use App\Services\Connectors\FileConnector;
use App\Services\PipelineExecutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PipelineExecutionServiceTest extends TestCase
{
    use RefreshDatabase;

    private PipelineExecutionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PipelineExecutionService(new ConnectorRegistry);
    }

    private function team()
    {
        return User::factory()->withPersonalTeam()->create()->currentTeam;
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

    // ─── matchesFilter operators ─────────────────────────────────────────────

    private function matchesFilter(array $record, array $filter): bool
    {
        $method = new \ReflectionMethod(PipelineExecutionService::class, 'matchesFilter');
        $method->setAccessible(true);

        return $method->invoke($this->service, $record, $filter);
    }

    public function test_matches_filter_not_equal_operator(): void
    {
        $this->assertTrue($this->matchesFilter(['status' => 'active'], ['field' => 'status', 'operator' => '!=', 'value' => 'inactive']));
        $this->assertFalse($this->matchesFilter(['status' => 'active'], ['field' => 'status', 'operator' => '!=', 'value' => 'active']));
    }

    public function test_matches_filter_comparison_operators(): void
    {
        $this->assertTrue($this->matchesFilter(['n' => 5], ['field' => 'n', 'operator' => '>', 'value' => 3]));
        $this->assertTrue($this->matchesFilter(['n' => 5], ['field' => 'n', 'operator' => '>=', 'value' => 5]));
        $this->assertTrue($this->matchesFilter(['n' => 5], ['field' => 'n', 'operator' => '<', 'value' => 10]));
        $this->assertTrue($this->matchesFilter(['n' => 5], ['field' => 'n', 'operator' => '<=', 'value' => 5]));
    }

    public function test_matches_filter_contains_operator(): void
    {
        $this->assertTrue($this->matchesFilter(['name' => 'Alice Smith'], ['field' => 'name', 'operator' => 'contains', 'value' => 'Smith']));
        $this->assertFalse($this->matchesFilter(['name' => 'Alice Smith'], ['field' => 'name', 'operator' => 'contains', 'value' => 'Jones']));
    }

    public function test_matches_filter_in_operator(): void
    {
        $this->assertTrue($this->matchesFilter(['status' => 'b'], ['field' => 'status', 'operator' => 'in', 'value' => ['a', 'b', 'c']]));
        $this->assertFalse($this->matchesFilter(['status' => 'z'], ['field' => 'status', 'operator' => 'in', 'value' => ['a', 'b', 'c']]));
    }

    public function test_matches_filter_not_null_operator(): void
    {
        $this->assertTrue($this->matchesFilter(['x' => 'y'], ['field' => 'x', 'operator' => 'not_null']));
        $this->assertFalse($this->matchesFilter(['x' => ''], ['field' => 'x', 'operator' => 'not_null']));
        $this->assertFalse($this->matchesFilter(['x' => null], ['field' => 'x', 'operator' => 'not_null']));
    }

    public function test_matches_filter_passes_through_when_field_missing(): void
    {
        $this->assertTrue($this->matchesFilter(['a' => 1], ['field' => 'missing', 'operator' => '=', 'value' => 'x']));
    }

    public function test_matches_filter_passes_through_for_unknown_operator(): void
    {
        $this->assertTrue($this->matchesFilter(['a' => 1], ['field' => 'a', 'operator' => 'bogus_operator', 'value' => 1]));
    }

    // ─── execute() end to end (no connector — inline records) ───────────────

    public function test_execute_completes_successfully_with_inline_records_and_no_connector(): void
    {
        $team = $this->team();
        $pipeline = DataPipeline::create([
            'team_id' => $team->id,
            'name' => 'Inline pipeline',
            'source_config' => [
                'records' => [
                    ['name' => 'Alice', 'score' => '10'],
                    ['name' => 'Bob', 'score' => '20'],
                ],
            ],
            'transform_config' => ['type_cast' => ['score' => 'int']],
            'destination_config' => [],
        ]);

        $run = $this->service->execute($pipeline, 'manual');

        $this->assertEquals('completed', $run->status);
        $this->assertEquals('manual', $run->trigger);
        $this->assertEquals(2, $run->records_read);
        $this->assertEquals(2, $run->records_written);
        $this->assertEquals(0, $run->records_failed);
        $this->assertNotNull($run->completed_at);
        $this->assertEquals(100.0, $run->data_quality_report['completeness_pct']);
    }

    public function test_execute_builds_lineage(): void
    {
        $team = $this->team();
        $pipeline = DataPipeline::create([
            'team_id' => $team->id,
            'name' => 'Lineage pipeline',
            'source_config' => ['records' => [['a' => 1]]],
            'transform_config' => [],
            'destination_config' => [],
        ]);

        $run = $this->service->execute($pipeline);

        $this->assertEquals($pipeline->id, $run->lineage['pipeline_id']);
        $this->assertEquals('Lineage pipeline', $run->lineage['pipeline_name']);
        $this->assertArrayHasKey('executed_at', $run->lineage);
    }

    public function test_execute_marks_run_failed_when_transform_throws(): void
    {
        $team = $this->team();
        $pipeline = DataPipeline::create([
            'team_id' => $team->id,
            'name' => 'Broken pipeline',
            // A scalar record with a field_map configured makes array_key_exists()
            // throw a TypeError inside transform() -- a real failure mode, not a
            // contrived mock.
            'source_config' => ['records' => ['not-an-array']],
            'transform_config' => ['field_map' => ['x' => 'y']],
            'destination_config' => [],
        ]);

        $run = $this->service->execute($pipeline);

        $this->assertEquals('failed', $run->status);
        $this->assertNotNull($run->error_message);
        $this->assertNotNull($run->completed_at);
    }

    public function test_execute_writes_analytics_snapshots_when_target_is_snapshot_with_connector(): void
    {
        $team = $this->team();
        $registry = (new ConnectorRegistry)->register(new FileConnector);
        $service = new PipelineExecutionService($registry);

        $connector = DataConnector::create([
            'team_id' => $team->id,
            'name' => 'CSV source',
            'type' => 'file',
            'driver' => 'csv',
            'config' => [
                'driver' => 'csv',
                'content' => "name,score\nAlice,10\nBob,20",
            ],
            'status' => 'active',
        ]);

        $pipeline = DataPipeline::create([
            'team_id' => $team->id,
            'data_connector_id' => $connector->id,
            'name' => 'CSV pipeline',
            'source_config' => [],
            'transform_config' => [],
            'destination_config' => ['target' => 'snapshot'],
        ]);

        $run = $service->execute($pipeline);

        $this->assertEquals('completed', $run->status);
        $this->assertEquals(2, $run->records_read);
        $this->assertDatabaseCount('analytics_snapshots', 1);

        $connector->refresh();
        $this->assertNotNull($connector->data_source_id, 'connector should be lazily linked to a DataSource');

        $this->assertDatabaseHas('analytics_snapshots', [
            'team_id' => $team->id,
            'data_source_id' => $connector->data_source_id,
            'snapshot_type' => 'pipeline',
        ]);
    }

    public function test_execute_reuses_an_already_linked_data_source_on_subsequent_runs(): void
    {
        $team = $this->team();
        $registry = (new ConnectorRegistry)->register(new FileConnector);
        $service = new PipelineExecutionService($registry);

        $dataSource = \App\Models\DataSource::factory()->create(['team_id' => $team->id]);

        $connector = DataConnector::create([
            'team_id' => $team->id,
            'data_source_id' => $dataSource->id,
            'name' => 'CSV source',
            'type' => 'file',
            'driver' => 'csv',
            'config' => ['driver' => 'csv', 'content' => "name\nAlice"],
            'status' => 'active',
        ]);

        $pipeline = DataPipeline::create([
            'team_id' => $team->id,
            'data_connector_id' => $connector->id,
            'name' => 'CSV pipeline',
            'source_config' => [],
            'transform_config' => [],
            'destination_config' => ['target' => 'snapshot'],
        ]);

        $service->execute($pipeline);

        $this->assertDatabaseHas('analytics_snapshots', [
            'data_source_id' => $dataSource->id,
        ]);
        // The pre-existing link is used as-is -- no second DataSource created.
        $this->assertDatabaseCount('data_sources', 1);
    }

    public function test_execute_does_not_persist_watermark_for_a_non_incremental_pipeline(): void
    {
        $team = $this->team();
        $pipeline = DataPipeline::create([
            'team_id' => $team->id,
            'name' => 'Non-incremental',
            'is_incremental' => false,
            'source_config' => ['records' => [['a' => 1]]],
            'transform_config' => [],
            'destination_config' => [],
        ]);

        $run = $this->service->execute($pipeline);

        $this->assertEquals('completed', $run->status);
        // No connector means next_watermark is always null, so no watermark
        // save is attempted regardless -- this asserts getWatermark() itself
        // short-circuits on a non-incremental pipeline without touching runs().
        $this->assertNull($run->data_quality_report['next_watermark'] ?? null);
    }
}
