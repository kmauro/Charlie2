<?php

namespace Database\Factories\Domains\Coverages\Models;

use App\Domains\Coverages\Models\CoveragePlan;
use App\Domains\Coverages\Models\CoveragePlanBenefit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CoveragePlanBenefit>
 */
class CoveragePlanBenefitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'coverage_plan_id' => CoveragePlan::factory(),
            'name' => fake()->words(3, true),
            'coverage_percentage' => 80,
            'copay_amount' => 1000,
            'annual_limit' => 12,
            'is_active' => true,
        ];
    }
}
