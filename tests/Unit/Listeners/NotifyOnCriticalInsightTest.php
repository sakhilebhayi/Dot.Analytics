<?php

namespace Tests\Unit\Listeners;

use App\Events\Analytics\CriticalInsightDiscovered;
use App\Listeners\Analytics\NotifyOnCriticalInsight;
use App\Models\AnalyticsAlert;
use App\Models\CrossPlatformInsight;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotifyOnCriticalInsightTest extends TestCase
{
    use RefreshDatabase;

    public function test_listener_creates_critical_alert(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $insight = CrossPlatformInsight::factory()->create([
            'team_id' => $team->id,
            'title' => 'Critical pattern detected',
            'narrative' => 'Three platforms show correlated failures.',
            'severity' => 'critical',
            'platforms_involved' => ['dot.fleet', 'dot.hr'],
        ]);

        $listener = new NotifyOnCriticalInsight;
        $listener->handle(new CriticalInsightDiscovered($insight));

        $this->assertDatabaseHas('analytics_alerts', [
            'team_id' => $team->id,
            'title' => 'Critical pattern detected',
            'severity' => 'critical',
            'status' => 'open',
        ]);
    }

    public function test_created_alert_references_source_insight(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $insight = CrossPlatformInsight::factory()->create([
            'team_id' => $user->currentTeam->id,
            'severity' => 'critical',
        ]);

        $listener = new NotifyOnCriticalInsight;
        $listener->handle(new CriticalInsightDiscovered($insight));

        $alert = AnalyticsAlert::where('team_id', $user->currentTeam->id)->first();
        $this->assertEquals($insight->id, $alert->context['insight_id']);
        $this->assertEquals('cross_platform_intelligence', $alert->context['source']);
    }
}
