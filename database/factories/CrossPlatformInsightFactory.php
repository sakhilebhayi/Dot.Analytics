<?php

namespace Database\Factories;

use App\Models\CrossPlatformInsight;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CrossPlatformInsight>
 */
class CrossPlatformInsightFactory extends Factory
{
    protected $model = CrossPlatformInsight::class;

    public function definition(): array
    {
        $platforms = ['dot.fleet', 'dot.crm', 'dot.hr', 'dot.payments', 'dot.support'];

        return [
            'team_id' => Team::factory(),
            'title' => $this->faker->sentence(6),
            'narrative' => $this->faker->paragraph(),
            'platforms_involved' => $this->faker->randomElements($platforms, 2),
            'entities_involved' => null,
            'insight_type' => $this->faker->randomElement(['correlation', 'causation', 'prediction', 'risk', 'opportunity']),
            'confidence' => $this->faker->randomFloat(2, 0.5, 0.99),
            'severity' => $this->faker->randomElement(['info', 'warning', 'critical']),
            'status' => 'new',
            'supporting_metrics' => null,
        ];
    }

    public function critical(): static
    {
        return $this->state(['severity' => 'critical']);
    }

    public function reviewed(): static
    {
        return $this->state(['status' => 'reviewed']);
    }
}
