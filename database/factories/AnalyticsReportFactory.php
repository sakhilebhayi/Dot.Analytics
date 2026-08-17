<?php

namespace Database\Factories;

use App\Models\AnalyticsReport;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnalyticsReport>
 */
class AnalyticsReportFactory extends Factory
{
    protected $model = AnalyticsReport::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'user_id' => User::factory(),
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->sentence(),
            'type' => 'ad_hoc',
            'config' => ['report_type' => $this->faker->randomElement(['insights', 'alerts', 'recommendations', 'metrics'])],
        ];
    }
}
