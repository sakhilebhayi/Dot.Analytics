<?php

namespace Tests\Unit\Models;

use App\Models\AnalyticsDashboard;
use App\Models\AnalyticsReport;
use App\Models\AnalyticsSnapshot;
use App\Models\BusinessDnaProfile;
use App\Models\DataSource;
use App\Models\ExecutiveBriefing;
use App\Models\IntelligenceEngineRun;
use App\Models\MetricDefinition;
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

    // ─── MetricDefinition ────────────────────────────────────────────────────

    public function test_metric_definition_has_computed_metrics_relationship(): void
    {
        $def = MetricDefinition::create([
            'key' => 'test.metric',
            'label' => 'Test Metric',
            'source_platform' => 'tasks',
            'engine' => 'operational',
            'aggregation' => 'avg',
        ]);

        $this->assertInstanceOf(Collection::class, $def->computedMetrics);
        $this->assertInstanceOf(Collection::class, $def->alerts);
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
}
