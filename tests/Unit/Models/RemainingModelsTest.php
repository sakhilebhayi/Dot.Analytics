<?php

namespace Tests\Unit\Models;

use App\Models\AiModelUsage;
use App\Models\AnalyticsReport;
use App\Models\AnalyticsSnapshot;
use App\Models\ComputedMetric;
use App\Models\DataPipeline;
use App\Models\DataSource;
use App\Models\MetricDefinition;
use App\Models\PipelineRun;
use App\Models\Recommendation;
use App\Models\ReportRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RemainingModelsTest extends TestCase
{
    use RefreshDatabase;

    private function team()
    {
        return User::factory()->withPersonalTeam()->create()->currentTeam;
    }

    // ─── ComputedMetric ──────────────────────────────────────────────────────

    public function test_computed_metric_belongs_to_team(): void
    {
        $team = $this->team();
        $def = MetricDefinition::create(['key' => 'test.m', 'label' => 'T', 'source_platform' => 'dot.fleet', 'engine' => 'operational', 'aggregation' => 'avg']);

        $metric = ComputedMetric::create([
            'team_id' => $team->id,
            'metric_definition_id' => $def->id,
            'value' => 42.5,
            'period' => 'daily',
            'period_date' => today()->toDateString(),
        ]);

        $this->assertEquals($team->id, $metric->team->id);
        $this->assertEquals($def->id, $metric->metricDefinition->id);
    }

    public function test_computed_metric_value_is_cast_to_decimal(): void
    {
        $team = $this->team();
        $def = MetricDefinition::create(['key' => 'test.cast', 'label' => 'Cast', 'source_platform' => 'dot.crm', 'engine' => 'customer', 'aggregation' => 'sum']);

        $metric = ComputedMetric::create([
            'team_id' => $team->id,
            'metric_definition_id' => $def->id,
            'value' => '99.99',
            'period' => 'weekly',
            'period_date' => today()->toDateString(),
        ]);

        $this->assertIsFloat((float) $metric->value);
    }

    // ─── DataPipeline ────────────────────────────────────────────────────────

    public function test_data_pipeline_belongs_to_team(): void
    {
        $team = $this->team();
        $pipe = DataPipeline::create([
            'team_id' => $team->id,
            'name' => 'Fleet Pipeline',
            'source_config' => [],
            'transform_config' => [],
            'destination_config' => [],
        ]);

        $this->assertEquals($team->id, $pipe->team->id);
    }

    public function test_data_pipeline_last_run_returns_null_when_no_runs(): void
    {
        $team = $this->team();
        $pipe = DataPipeline::create([
            'team_id' => $team->id,
            'name' => 'No Runs',
            'source_config' => [],
            'transform_config' => [],
            'destination_config' => [],
        ]);

        $this->assertNull($pipe->lastRun());
    }

    public function test_data_pipeline_last_run_returns_most_recent(): void
    {
        $team = $this->team();
        $pipe = DataPipeline::create([
            'team_id' => $team->id,
            'name' => 'With Runs',
            'source_config' => [],
            'transform_config' => [],
            'destination_config' => [],
        ]);

        $runA = PipelineRun::create(['data_pipeline_id' => $pipe->id, 'status' => 'completed', 'started_at' => now()->subHours(2)]);
        $runB = PipelineRun::create(['data_pipeline_id' => $pipe->id, 'status' => 'completed', 'started_at' => now()]);

        $this->assertEquals($runB->id, $pipe->lastRun()->id);
    }

    public function test_data_pipeline_is_incremental_defaults_true(): void
    {
        $team = $this->team();
        $pipe = DataPipeline::create([
            'team_id' => $team->id, 'name' => 'Default', 'source_config' => [], 'transform_config' => [], 'destination_config' => [],
        ]);

        $this->assertTrue($pipe->is_incremental);
    }

    // ─── AiModelUsage ────────────────────────────────────────────────────────

    public function test_ai_model_usage_total_cost_for_team(): void
    {
        $team = $this->team();

        AiModelUsage::create(['team_id' => $team->id, 'provider' => 'anthropic', 'model' => 'claude-sonnet-4-6', 'capability' => 'insight', 'input_tokens' => 100, 'output_tokens' => 50, 'cost_usd' => 0.005]);
        AiModelUsage::create(['team_id' => $team->id, 'provider' => 'openai', 'model' => 'gpt-4o', 'capability' => 'query', 'input_tokens' => 200, 'output_tokens' => 100, 'cost_usd' => 0.010]);

        $total = AiModelUsage::totalCostForTeam($team->id);

        $this->assertEqualsWithDelta(0.015, $total, 0.0001);
    }

    public function test_ai_model_usage_total_cost_zero_for_new_team(): void
    {
        $team = $this->team();
        $this->assertEquals(0.0, AiModelUsage::totalCostForTeam($team->id));
    }

    // ─── AnalyticsSnapshot ───────────────────────────────────────────────────

    public function test_analytics_snapshot_payload_is_array(): void
    {
        $team = $this->team();
        $source = DataSource::factory()->create(['team_id' => $team->id]);

        $snap = AnalyticsSnapshot::create([
            'team_id' => $team->id,
            'data_source_id' => $source->id,
            'snapshot_type' => 'daily',
            'payload' => ['vehicles' => 10, 'idle_rate' => 0.15],
            'captured_at' => now(),
        ]);

        $this->assertIsArray($snap->payload);
        $this->assertEquals(10, $snap->payload['vehicles']);
    }

    // ─── AnalyticsReport ─────────────────────────────────────────────────────

    public function test_analytics_report_latest_run_is_has_one_latest(): void
    {
        $team = $this->team();
        $report = AnalyticsReport::create([
            'team_id' => $team->id,
            'user_id' => $team->user_id,
            'title' => 'Test',
            'type' => 'ad_hoc',
            'config' => [],
        ]);

        $runA = ReportRun::create(['analytics_report_id' => $report->id, 'status' => 'completed']);
        $runB = ReportRun::create(['analytics_report_id' => $report->id, 'status' => 'completed']);

        $this->assertEquals($runB->id, $report->latestRun->id);
    }

    // ─── Recommendation ──────────────────────────────────────────────────────

    public function test_recommendation_supporting_data_is_array(): void
    {
        $team = $this->team();
        $rec = Recommendation::factory()->create([
            'team_id' => $team->id,
            'supporting_data' => ['confidence' => 0.9, 'ai_model' => 'claude-sonnet-4-6'],
        ]);

        $this->assertIsArray($rec->supporting_data);
        $this->assertEquals(0.9, $rec->supporting_data['confidence']);
    }
}
