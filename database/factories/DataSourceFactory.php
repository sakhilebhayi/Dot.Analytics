<?php

namespace Database\Factories;

use App\Models\DataSource;
use App\Models\Team;
use App\Services\IntelligenceEngineService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DataSource>
 */
class DataSourceFactory extends Factory
{
    protected $model = DataSource::class;

    public function definition(): array
    {
        $platforms = array_keys(IntelligenceEngineService::PLATFORMS);
        $platform = $this->faker->randomElement($platforms);
        $catalog = IntelligenceEngineService::PLATFORMS[$platform];

        return [
            'team_id' => Team::factory(),
            'platform' => $platform,
            'display_name' => $catalog['label'],
            'base_url' => null,
            'status' => 'pending',
            'config' => [],
            'capabilities' => $catalog['contributions'],
        ];
    }

    public function connected(): static
    {
        return $this->state([
            'status' => 'connected',
            'connected_at' => now(),
        ]);
    }

    public function forPlatform(string $platformKey): static
    {
        $catalog = IntelligenceEngineService::PLATFORMS[$platformKey] ?? [];

        return $this->state([
            'platform' => $platformKey,
            'display_name' => $catalog['label'] ?? $platformKey,
        ]);
    }
}
