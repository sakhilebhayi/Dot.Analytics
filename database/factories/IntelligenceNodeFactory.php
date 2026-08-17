<?php

namespace Database\Factories;

use App\Models\IntelligenceNode;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IntelligenceNode>
 */
class IntelligenceNodeFactory extends Factory
{
    protected $model = IntelligenceNode::class;

    public function definition(): array
    {
        $types = ['customer', 'invoice', 'equipment', 'operator', 'contract', 'order', 'ticket', 'project', 'supplier', 'asset', 'employee'];

        return [
            'team_id' => Team::factory(),
            'entity_type' => $this->faker->randomElement($types),
            'entity_id' => strtoupper($this->faker->bothify('???-####')),
            'label' => $this->faker->company(),
            'source_platform' => $this->faker->randomElement(['dot.fleet', 'dot.crm', 'dot.hr']),
            'attributes' => [],
        ];
    }
}
