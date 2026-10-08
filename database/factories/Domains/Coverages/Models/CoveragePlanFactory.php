<?php

namespace Database\Factories\Domains\Coverages\Models;

use App\Domains\Coverages\Models\CoveragePlan;
use App\Domains\Organizations\Models\LegalEntity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CoveragePlan>
 */
class CoveragePlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'legal_entity_id' => LegalEntity::factory(),
            'name' => fake()->words(2, true),
            'code' => fake()->unique()->bothify('PLAN-###'),
            'billing_cycle' => 'monthly',
            'recurring_amount' => fake()->randomFloat(2, 1000, 10000),
            'is_active' => true,
        ];
    }
}
