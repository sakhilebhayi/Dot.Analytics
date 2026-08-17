<?php

namespace Database\Factories;

use App\Models\ComputedMetric;
use App\Models\MetricDefinition;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComputedMetric>
 */
class ComputedMetricFactory extends Factory
{
    protected $model = ComputedMetric::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'metric_definition_id' => MetricDefinition::factory(),
            'value' => $this->faker->randomFloat(2, 0, 1000),
            'period' => 'daily',
            'period_date' => $this->faker->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
        ];
    }
}
