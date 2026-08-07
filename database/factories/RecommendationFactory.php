<?php

namespace Database\Factories;

use App\Models\Recommendation;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recommendation>
 */
class RecommendationFactory extends Factory
{
    protected $model = Recommendation::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'engine' => $this->faker->randomElement(['operational', 'financial', 'risk', 'customer', 'decision']),
            'title' => $this->faker->sentence(6),
            'rationale' => $this->faker->paragraph(),
            'priority' => $this->faker->randomElement(['critical', 'high', 'medium', 'low']),
            'status' => 'pending',
        ];
    }
}
