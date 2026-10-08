<?php

namespace Database\Factories\Domains\Coverages\Models;

use App\Domains\Coverages\Models\CoverageContract;
use App\Domains\Coverages\Models\CoveragePlan;
use App\Domains\Organizations\Models\LegalEntity;
use App\Domains\Organizations\Models\Location;
use App\Domains\Patients\Models\Patient;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CoverageContract>
 */
class CoverageContractFactory extends Factory
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
            'location_id' => Location::factory(),
            'patient_id' => Patient::factory(),
            'coverage_plan_id' => CoveragePlan::factory(),
            'created_by' => User::factory(),
            'payer_type' => 'patient',
            'coverage_amount' => 0,
            'recurring_amount' => 5000,
            'starts_on' => today(),
            'next_charge_on' => today(),
            'status' => 'active',
        ];
    }
}
