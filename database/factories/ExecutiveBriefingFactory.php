<?php

namespace Database\Factories;

use App\Models\ExecutiveBriefing;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExecutiveBriefing>
 */
class ExecutiveBriefingFactory extends Factory
{
    protected $model = ExecutiveBriefing::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'period' => 'weekly',
            'period_date' => now()->toDateString(),
            'status' => 'ready',
            'summary' => $this->faker->paragraph(),
            'highlights' => [$this->faker->sentence()],
            'risks' => [$this->faker->sentence()],
            'recommendations' => [],
            'engines_consulted' => ['business', 'operational'],
            'insight_count' => $this->faker->numberBetween(0, 20),
        ];
    }
}
