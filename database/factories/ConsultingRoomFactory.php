<?php

namespace Database\Factories;

use App\Domains\Organizations\Models\Location;
use App\Domains\Professionals\Models\ConsultingRoom;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConsultingRoom>
 */
class ConsultingRoomFactory extends Factory
{
    protected $model = ConsultingRoom::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'location_id' => Location::factory(),
            'name' => 'Consultorio '.fake()->unique()->numberBetween(1, 500),
            'code' => fake()->unique()->bothify('CONS-###'),
            'sector' => fake()->optional()->randomElement(['Ambulatorio', 'Diagnóstico', 'Pediatría']),
            'floor' => fake()->optional()->randomElement(['PB', '1', '2']),
            'equipment' => fake()->randomElements(['Camilla', 'ECG', 'Oxígeno'], fake()->numberBetween(0, 2)),
            'operational_restrictions' => null,
            'is_active' => true,
        ];
    }
}
