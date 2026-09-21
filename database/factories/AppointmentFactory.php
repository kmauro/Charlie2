<?php

namespace Database\Factories;

use App\Domains\Appointments\Enums\AppointmentStatus;
use App\Domains\Appointments\Enums\AppointmentType;
use App\Domains\Appointments\Models\Appointment;
use App\Domains\Organizations\Models\Location;
use App\Domains\Patients\Models\Patient;
use App\Domains\Professionals\Models\Professional;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Appointment> */
class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $startsAt = now()->addDays(fake()->numberBetween(2, 30))->setTime(
            fake()->numberBetween(8, 17),
            fake()->randomElement([0, 30]),
        );

        return [
            'patient_id' => Patient::factory(),
            'professional_id' => Professional::factory(),
            'location_id' => Location::factory(),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->clone()->addMinutes(30),
            'duration_minutes' => 30,
            'type' => AppointmentType::InPerson,
            'status' => AppointmentStatus::Reserved,
            'office' => 'Consultorio '.fake()->numberBetween(1, 20),
            'reason' => fake()->optional()->sentence(),
            'is_overbook' => false,
            'cancellation_reason' => null,
            'cancelled_at' => null,
            'cancelled_by' => null,
            'lock_version' => 1,
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => AppointmentStatus::Cancelled,
            'cancellation_reason' => 'Cancelado por el paciente.',
            'cancelled_at' => now(),
        ]);
    }
}
