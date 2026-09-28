<?php

namespace Database\Factories;

use App\Domains\Organizations\Models\Location;
use App\Domains\Professionals\Models\LocationHoliday;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LocationHoliday>
 */
class LocationHolidayFactory extends Factory
{
    protected $model = LocationHoliday::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'location_id' => Location::factory(),
            'date' => fake()->dateTimeBetween('now', '+1 year'),
            'name' => 'Feriado '.fake()->words(2, true),
        ];
    }
}
