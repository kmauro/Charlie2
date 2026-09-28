<?php

namespace Database\Factories;

use App\Domains\Organizations\Models\Location;
use App\Domains\Professionals\Enums\ScheduleExceptionType;
use App\Domains\Professionals\Models\Professional;
use App\Domains\Professionals\Models\ProfessionalScheduleException;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProfessionalScheduleException>
 */
class ProfessionalScheduleExceptionFactory extends Factory
{
    protected $model = ProfessionalScheduleException::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'professional_id' => Professional::factory(),
            'location_id' => Location::factory(),
            'date' => fake()->dateTimeBetween('now', '+6 months'),
            'type' => ScheduleExceptionType::Unavailable,
            'start_time' => null,
            'end_time' => null,
            'reason' => 'Excepción programada',
        ];
    }
}
