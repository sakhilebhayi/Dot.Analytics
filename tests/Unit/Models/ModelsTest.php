<?php

namespace Tests\Unit\Models;

use App\Models\AnalyticsDashboard;
use App\Models\AnalyticsReport;
use App\Models\AnalyticsSnapshot;
use App\Models\BusinessDnaProfile;
use App\Models\DataConnector;
use App\Models\DataPipeline;
use App\Models\DataSource;
use App\Models\ExecutiveBriefing;
use App\Models\IntelligenceEdge;
use App\Models\IntelligenceEngineRun;
use App\Models\IntelligenceNode;
use App\Models\MetricDefinition;
use App\Models\PipelineRun;
use App\Models\ReportRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelsTest extends TestCase
{
    use RefreshDatabase;

    private function team()
    {
        return User::factory()->withPersonalTeam()->create()->currentTeam;
    }

    // ─── AnalyticsDashboard ───────────────────────────────────────────────────

    public function test_analytics_dashboard_belongs_to_team(): void
    {
        $team = $this->team();
        $dash = AnalyticsDashboard::create([
            'team_id' => $team->id,
            'user_id' => $team->user_id,
            'title' => 'Test Dashboard',
        ]);

        $this->assertEquals($team->id, $dash->team->id);
    }

    public function test_analytics_dashboard_has_widgets_relationship(): void
    {
        $team = $this->team();
        $dash = AnalyticsDashboard::create(['team_id' => $team->id, 'user_id' => $team->user_id, 'title' => 'T']);

        $this->assertInstanceOf(Collection::class, $dash->widgets);
    }

    // ─── AnalyticsSnapshot ───────────────────────────────────────────────────

    public function test_analytics_snapshot_belongs_to_team(): void
    {
        $team = $this->team();
        $source = DataSource::factory()->create(['team_id' => $team->id]);
        $snap = AnalyticsSnapshot::create([
            'team_id' => $team->id,
            'data_source_id' => $source->id,
            'snapshot_type' => 'hourly',
            'payload' => ['key' => 'value'],
            'captured_at' => now(),
        ]);

        $this->assertEquals($team->id, $snap->team->id);
    }

    // ─── BusinessDnaProfile ──────────────────────────────────────────────────

    public function test_business_dna_profile_belongs_to_team(): void
    {
        $team = $this->team();
        $profile = BusinessDnaProfile::create([
            'team_id' => $team->id,
            'operational_patterns' => ['summary' => 'Normal ops'],
            'seasonal_trends' => [],
            'risk_tolerance' => ['level' => 'medium'],
            'growth_signals' => [],
            'confidence_score' => 0.5,
        ]);

        $this->assertEquals($team->id, $profile->team->id);
    }

    // ─── ExecutiveBriefing ───────────────────────────────────────────────────

    public function test_executive_briefing_is_ready(): void
    {
        $team = $this->team();
        $b = ExecutiveBriefing::create([
            'team_id' => $team->id,
            'period' => 'weekly',
            'period_date' => now()->toDateString(),
            'status' => 'ready',
        ]);

        $this->assertTrue($b->isReady());
        $this->assertEquals($team->id, $b->team->id);
    }

    public function test_executive_briefing_is_not_ready_when_generating(): void
    {
        $team = $this->team();
        $b = ExecutiveBriefing::create([
            'team_id' => $team->id,
            'period' => 'daily',
            'period_date' => now()->toDateString(),
            'status' => 'generating',
        ]);

        $this->assertFalse($b->isReady());
    }

    // ─── IntelligenceEngineRun ───────────────────────────────────────────────

    public function test_intelligence_engine_run_duration_seconds(): void
    {
        $team = $this->team();
        $run = IntelligenceEngineRun::create([
            'team_id' => $team->id,
            'engine' => 'operational',
            'status' => 'completed',
            'started_at' => now()->subSeconds(30),
            'completed_at' => now(),
        ]);

        $duration = $run->durationSeconds();
        $this->assertGreaterThanOrEqual(29, $duration);
        $this->assertLessThanOrEqual(31, $duration);
    }

    public function test_intelligence_engine_run_duration_null_when_not_completed(): void
    {
        $team = $this->team();
        $run = IntelligenceEngineRun::create([
            'team_id' => $team->id,
            'engine' => 'operational',
            'status' => 'running',
        ]);

        $this->assertNull($run->durationSeconds());
    }

    // ─── DataConnector ───────────────────────────────────────────────────────

    public function test_data_connector_is_active(): void
    {
        $team = $this->team();
        $conn = DataConnector::create([
            'team_id' => $team->id,
            'name' => 'My DB',
            'type' => 'database',
            'driver' => 'postgres',
            'config' => [],
            'status' => 'active',
        ]);

        $this->assertTrue($conn->isActive());
    }

    public function test_data_connector_is_not_active_when_inactive(): void
    {
        $team = $this->team();
        $conn = DataConnector::create([
            'team_id' => $team->id, 'name' => 'DB', 'type' => 'database',
            'driver' => 'postgres', 'config' => [], 'status' => 'inactive',
        ]);

        $this->assertFalse($conn->isActive());
    }

    // ─── MetricDefinition ────────────────────────────────────────────────────

    public function test_metric_definition_has_computed_metrics_relationship(): void
    {
        $def = MetricDefinition::create([
            'key' => 'test.metric',
            'label' => 'Test Metric',
            'source_platform' => 'dot.fleet',
            'engine' => 'operational',
            'aggregation' => 'avg',
        ]);

        $this->assertInstanceOf(Collection::class, $def->computedMetrics);
        $this->assertInstanceOf(Collection::class, $def->alerts);
    }

    // ─── PipelineRun ─────────────────────────────────────────────────────────

    public function test_pipeline_run_quality_score(): void
    {
        $team = $this->team();
        $pipeline = DataPipeline::create([
            'team_id' => $team->id,
            'name' => 'Test',
            'source_config' => [],
            'transform_config' => [],
            'destination_config' => [],
        ]);

        $run = PipelineRun::create([
            'data_pipeline_id' => $pipeline->id,
            'status' => 'completed',
            'data_quality_report' => ['completeness_pct' => 92],
        ]);

        $this->assertEquals(92, $run->qualityScore());
    }

    // ─── ReportRun ───────────────────────────────────────────────────────────

    public function test_report_run_belongs_to_report(): void
    {
        $team = $this->team();
        $report = AnalyticsReport::create([
            'team_id' => $team->id,
            'user_id' => $team->user_id,
            'title' => 'Test',
            'type' => 'ad_hoc',
            'config' => [],
        ]);

        $run = ReportRun::create(['analytics_report_id' => $report->id, 'status' => 'completed']);

        $this->assertEquals($report->id, $run->report->id);
    }

    // ─── IntelligenceNode + Edge ─────────────────────────────────────────────

    public function test_intelligence_node_has_edge_relationships(): void
    {
        $team = $this->team();
        $nodeA = IntelligenceNode::create([
            'team_id' => $team->id,
            'entity_type' => 'customer',
            'entity_id' => 'C-001',
            'label' => 'Acme Corp',
            'source_platform' => 'dot.crm',
        ]);
        $nodeB = IntelligenceNode::create([
            'team_id' => $team->id,
            'entity_type' => 'invoice',
            'entity_id' => 'I-001',
            'label' => 'Invoice #1',
            'source_platform' => 'dot.payments',
        ]);

        IntelligenceEdge::create([
            'team_id' => $team->id,
            'from_node_id' => $nodeA->id,
            'to_node_id' => $nodeB->id,
            'relationship' => 'has',
        ]);

        $nodeA->load('outgoingEdges');
        $this->assertCount(1, $nodeA->outgoingEdges);
        $nodeB->load('incomingEdges');
        $this->assertCount(1, $nodeB->incomingEdges);
    }
}
