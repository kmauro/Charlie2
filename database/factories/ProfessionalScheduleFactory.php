<?php

namespace Database\Factories;

use App\Domains\Organizations\Models\Location;
use App\Domains\Professionals\Models\Professional;
use App\Domains\Professionals\Models\ProfessionalSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProfessionalSchedule>
 */
class ProfessionalScheduleFactory extends Factory
{
    protected $model = ProfessionalSchedule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startHour = fake()->numberBetween(7, 15);
        $startMinute = fake()->randomElement([0, 30]);
        $durationMinutes = fake()->randomElement([30, 60, 90, 120]);
        $startTotalMinutes = ($startHour * 60) + $startMinute;
        $endTotalMinutes = $startTotalMinutes + $durationMinutes;

        return [
            'professional_id' => Professional::factory(),
            'location_id' => null,
            'location_name' => fake()->randomElement([
                'Sede Central',
                'Sede Norte',
                'Centro Ambulatorio',
            ]),
            'weekday' => fake()->numberBetween(1, 7),
            'start_time' => sprintf('%02d:%02d', intdiv($startTotalMinutes, 60), $startTotalMinutes % 60),
            'end_time' => sprintf('%02d:%02d', intdiv($endTotalMinutes, 60), $endTotalMinutes % 60),
        ];
    }

    public function forLocation(Location $location): static
    {
        return $this->state(fn (): array => [
            'location_id' => $location->getKey(),
            'location_name' => $location->name,
        ]);
    }
}
