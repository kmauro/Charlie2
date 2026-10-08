<?php

namespace Database\Factories\Domains\Coverages\Models;

use App\Domains\Coverages\Models\CoverageContract;
use App\Domains\Coverages\Models\CoverageContractCharge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CoverageContractCharge>
 */
class CoverageContractChargeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'coverage_contract_id' => CoverageContract::factory(),
            'due_date' => today(),
            'amount' => 5000,
            'status' => 'issued',
        ];
    }
}
