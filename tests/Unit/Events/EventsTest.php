<?php

namespace Tests\Unit\Events;

use App\Events\Analytics\BusinessDnaRecomputed;
use App\Events\Analytics\CriticalInsightDiscovered;
use App\Events\Analytics\IntelligenceEngineCompleted;
use App\Events\Analytics\PlatformConnected;
use App\Events\Analytics\PlatformDisconnected;
use App\Models\CrossPlatformInsight;
use App\Models\DataSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventsTest extends TestCase
{
    use RefreshDatabase;

    // ─── BusinessDnaRecomputed ───────────────────────────────────────────────

    public function test_business_dna_recomputed_confidence_improved_true(): void
    {
        $event = new BusinessDnaRecomputed(1, 0.8, 0.5);
        $this->assertTrue($event->confidenceImproved());
    }

    public function test_business_dna_recomputed_confidence_improved_false(): void
    {
        $event = new BusinessDnaRecomputed(1, 0.4, 0.8);
        $this->assertFalse($event->confidenceImproved());
    }

    public function test_business_dna_recomputed_confidence_equal_is_not_improved(): void
    {
        $event = new BusinessDnaRecomputed(1, 0.6, 0.6);
        $this->assertFalse($event->confidenceImproved());
    }

    public function test_business_dna_recomputed_carries_correct_scores(): void
    {
        $event = new BusinessDnaRecomputed(42, 0.75, 0.50);

        $this->assertEquals(42, $event->teamId);
        $this->assertEquals(0.75, $event->confidenceScore);
        $this->assertEquals(0.50, $event->previousConfidenceScore);
    }

    // ─── IntelligenceEngineCompleted ─────────────────────────────────────────

    public function test_intelligence_engine_completed_carries_all_fields(): void
    {
        $event = new IntelligenceEngineCompleted(1, 'operational', 5, 3, ['dot.fleet'], 42);

        $this->assertEquals(1, $event->teamId);
        $this->assertEquals('operational', $event->engine);
        $this->assertEquals(5, $event->insightsGenerated);
        $this->assertEquals(3, $event->metricsComputed);
        $this->assertEquals(['dot.fleet'], $event->platformsConsumed);
        $this->assertEquals(42, $event->runId);
    }

    public function test_intelligence_engine_completed_broadcasts_correctly(): void
    {
        $event = new IntelligenceEngineCompleted(1, 'financial', 2, 0, ['dot.payments'], 1);

        $this->assertEquals('engine.completed', $event->broadcastAs());
        $payload = $event->broadcastWith();

        $this->assertArrayHasKey('engine', $payload);
        $this->assertArrayHasKey('insights_generated', $payload);
        $this->assertEquals('financial', $payload['engine']);
    }

    public function test_intelligence_engine_completed_broadcasts_on_team_channel(): void
    {
        $event    = new IntelligenceEngineCompleted(99, 'risk', 1, 0, [], 1);
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertStringContainsString('team.99.intelligence', $channels[0]->name);
    }

    // ─── PlatformConnected ───────────────────────────────────────────────────

    public function test_platform_connected_carries_data_source_and_team_id(): void
    {
        $user   = User::factory()->withPersonalTeam()->create();
        $source = DataSource::factory()->create(['team_id' => $user->currentTeam->id]);
        $event  = new PlatformConnected($source, $user->currentTeam->id);

        $this->assertEquals($user->currentTeam->id, $event->teamId);
        $this->assertEquals($source->id, $event->dataSource->id);
    }

    // ─── PlatformDisconnected ────────────────────────────────────────────────

    public function test_platform_disconnected_carries_platform_and_team(): void
    {
        $event = new PlatformDisconnected(5, 'dot.fleet', 'Dot.Fleet');

        $this->assertEquals(5, $event->teamId);
        $this->assertEquals('dot.fleet', $event->platform);
        $this->assertEquals('Dot.Fleet', $event->displayName);
    }

    // ─── CriticalInsightDiscovered ───────────────────────────────────────────

    public function test_critical_insight_discovered_carries_insight(): void
    {
        $user    = User::factory()->withPersonalTeam()->create();
        $insight = CrossPlatformInsight::factory()->create([
            'team_id'  => $user->currentTeam->id,
            'severity' => 'critical',
        ]);

        $event = new CriticalInsightDiscovered($insight);

        $this->assertEquals($insight->id, $event->insight->id);
        $this->assertEquals('critical', $event->insight->severity);
    }
}
