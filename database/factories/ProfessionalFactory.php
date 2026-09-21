<?php

namespace Database\Factories;

use App\Domains\Professionals\Enums\OfficeAssignmentType;
use App\Domains\Professionals\Enums\ProfessionalStatus;
use App\Domains\Professionals\Models\Professional;
use App\Domains\Users\Enums\RoleCode;
use App\Domains\Users\Enums\UserPermission;
use App\Domains\Users\Models\Role;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Professional>
 */
class ProfessionalFactory extends Factory
{
    protected $model = Professional::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(fn (): array => [
                'role_id' => Role::query()->firstOrCreate(
                    ['code' => RoleCode::Professional->value],
                    ['name' => RoleCode::Professional->label()],
                )->getKey(),
                'permissions' => [
                    UserPermission::ManageAppointments->value,
                    UserPermission::AccessExternalClinicalRecord->value,
                ],
            ]),
            'document_number' => fake()->unique()->numerify('########'),
            'phone' => fake()->phoneNumber(),
            'specialty' => fake()->randomElement([
                'Cardiología',
                'Clínica médica',
                'Dermatología',
                'Pediatría',
                'Traumatología',
            ]),
            'registration_type' => fake()->randomElement(['M.N.', 'M.P.']),
            'registration_number' => fake()->unique()->numerify('######'),
            'appointment_duration_minutes' => fake()->randomElement([15, 20, 30, 45, 60]),
            'status' => ProfessionalStatus::Active,
            'office_assignment_type' => OfficeAssignmentType::Availability,
            'fixed_office' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'status' => ProfessionalStatus::Inactive,
        ]);
    }

    public function fixedOffice(?string $office = null): static
    {
        return $this->state(fn (): array => [
            'office_assignment_type' => OfficeAssignmentType::Fixed,
            'fixed_office' => $office ?? 'Consultorio '.fake()->numberBetween(1, 30),
        ]);
    }
}
