<?php

namespace Database\Factories;

use App\Models\MetricDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MetricDefinition>
 */
class MetricDefinitionFactory extends Factory
{
    protected $model = MetricDefinition::class;

    public function definition(): array
    {
        $engines = ['business', 'financial', 'operational', 'people', 'customer', 'risk', 'predictive'];
        $platforms = ['dot.fleet', 'dot.crm', 'dot.hr', 'dot.payments', 'dot.support'];

        return [
            'key' => $this->faker->unique()->slug(3),
            'label' => $this->faker->words(3, true),
            'source_platform' => $this->faker->randomElement($platforms),
            'engine' => $this->faker->randomElement($engines),
            'aggregation' => $this->faker->randomElement(['sum', 'avg', 'count', 'latest']),
            'unit' => $this->faker->randomElement(['%', 'ZAR', 'count', 'score', 'hours']),
            'description' => $this->faker->sentence(),
        ];
    }
}
