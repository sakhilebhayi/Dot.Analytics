<?php

namespace Tests\Feature\Models;

use App\Models\AnalyticsDashboard;
use App\Models\DashboardWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_visibility_defaults_to_private(): void
    {
        $dashboard = AnalyticsDashboard::factory()->create();

        $this->assertSame('private', $dashboard->fresh()->visibility);
    }

    public function test_visibility_can_be_set_to_team(): void
    {
        $dashboard = AnalyticsDashboard::factory()->create(['visibility' => 'team']);

        $this->assertSame('team', $dashboard->fresh()->visibility);
    }

    public function test_widget_factory_creates_a_widget_linked_to_a_dashboard(): void
    {
        $dashboard = AnalyticsDashboard::factory()->create();
        $widget = DashboardWidget::factory()->create(['analytics_dashboard_id' => $dashboard->id]);

        $this->assertTrue($dashboard->widgets->contains($widget));
    }
}
