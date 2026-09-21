<?php

namespace Database\Factories;

use App\Domains\Patients\Enums\PatientStatus;
use App\Domains\Patients\Enums\RegisteredSex;
use App\Domains\Patients\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    protected $model = Patient::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'document_number' => fake()->unique()->numerify('########'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'birth_date' => fake()->dateTimeBetween('-90 years', '-1 year'),
            'registered_sex' => fake()->randomElement(RegisteredSex::cases()),
            'gender' => null,
            'phone' => fake()->optional()->phoneNumber(),
            'email' => fake()->optional()->safeEmail(),
            'address' => fake()->optional()->address(),
            'coverage_name' => 'Particular',
            'member_number' => null,
            'status' => PatientStatus::Active,
            'previous_status' => null,
            'archived_at' => null,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (): array => [
            'status' => PatientStatus::Archived,
            'previous_status' => PatientStatus::Active,
            'archived_at' => now(),
        ]);
    }

    public function inpatient(): static
    {
        return $this->state(fn (): array => [
            'status' => PatientStatus::Inpatient,
        ]);
    }

    public function minor(): static
    {
        return $this->state(fn (): array => [
            'birth_date' => fake()->dateTimeBetween('-17 years', '-1 year'),
        ]);
    }
}
