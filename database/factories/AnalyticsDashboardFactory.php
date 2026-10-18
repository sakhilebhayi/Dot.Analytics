<?php

namespace Database\Factories;

use App\Models\AnalyticsDashboard;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnalyticsDashboard>
 */
class AnalyticsDashboardFactory extends Factory
{
    protected $model = AnalyticsDashboard::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'user_id' => User::factory(),
            'title' => $this->faker->sentence(3),
            'is_default' => false,
            'layout' => null,
        ];
    }
}
