<?php

namespace Database\Factories;

use App\Models\AnalyticsDashboard;
use App\Models\DashboardWidget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DashboardWidget>
 */
class DashboardWidgetFactory extends Factory
{
    protected $model = DashboardWidget::class;

    public function definition(): array
    {
        return [
            'analytics_dashboard_id' => AnalyticsDashboard::factory(),
            'widget_type' => 'alert_feed',
            'title' => $this->faker->words(2, true),
            'config' => ['type' => 'alert_feed'],
            'col' => 0,
            'row' => 0,
            'width' => 4,
            'height' => 2,
        ];
    }
}
