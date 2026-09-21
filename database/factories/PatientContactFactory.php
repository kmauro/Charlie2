<?php

namespace Database\Factories;

use App\Domains\Patients\Models\Patient;
use App\Domains\Patients\Models\PatientContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatientContact>
 */
class PatientContactFactory extends Factory
{
    protected $model = PatientContact::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'full_name' => fake()->name(),
            'relationship' => fake()->randomElement(['Madre', 'Padre', 'Pareja', 'Hermano/a', 'Tutor/a']),
            'phone' => fake()->optional()->phoneNumber(),
            'email' => fake()->optional()->safeEmail(),
            'is_emergency_contact' => false,
            'is_legal_representative' => false,
            'is_financially_responsible' => false,
        ];
    }

    public function legalRepresentative(): static
    {
        return $this->state(fn (): array => [
            'is_emergency_contact' => true,
            'is_legal_representative' => true,
        ]);
    }
}
