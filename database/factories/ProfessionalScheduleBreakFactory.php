<?php

namespace Database\Factories;

use App\Domains\Professionals\Models\ProfessionalSchedule;
use App\Domains\Professionals\Models\ProfessionalScheduleBreak;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProfessionalScheduleBreak>
 */
class ProfessionalScheduleBreakFactory extends Factory
{
    protected $model = ProfessionalScheduleBreak::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'professional_schedule_id' => ProfessionalSchedule::factory(),
            'start_time' => '12:00',
            'end_time' => '13:00',
            'label' => 'Almuerzo',
        ];
    }
}
