<?php

namespace Database\Factories;

use App\Models\AnalyticsAlert;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnalyticsAlert>
 */
class AnalyticsAlertFactory extends Factory
{
    protected $model = AnalyticsAlert::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'title' => $this->faker->sentence(5),
            'description' => $this->faker->sentence(),
            'severity' => $this->faker->randomElement(['info', 'warning', 'critical']),
            'status' => 'open',
            'context' => [],
            'triggered_at' => now(),
        ];
    }
}
