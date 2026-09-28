<?php

namespace Database\Factories;

use App\Domains\Professionals\Models\Professional;
use App\Domains\Professionals\Models\ProfessionalLeave;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProfessionalLeave>
 */
class ProfessionalLeaveFactory extends Factory
{
    protected $model = ProfessionalLeave::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsOn = fake()->dateTimeBetween('now', '+6 months');

        return [
            'professional_id' => Professional::factory(),
            'location_id' => null,
            'starts_on' => $startsOn,
            'ends_on' => $startsOn,
            'reason' => 'Licencia programada',
        ];
    }
}
