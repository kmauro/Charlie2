<?php

namespace Database\Factories;

use App\Domains\Organizations\Enums\OrganizationStatus;
use App\Domains\Organizations\Models\LegalEntity;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LegalEntity> */
class LegalEntityFactory extends Factory
{
    protected $model = LegalEntity::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'business_name' => fake()->company(),
            'cuit' => $this->validCuit(),
            'tax_condition' => 'responsable_inscripto',
            'fiscal_address' => fake()->address(),
            'fiscal_point_of_sale' => null,
            'arca_environment' => null,
            'status' => OrganizationStatus::Active,
            'archived_at' => null,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (): array => [
            'status' => OrganizationStatus::Archived,
            'archived_at' => now(),
        ]);
    }

    private function validCuit(): string
    {
        $base = fake()->unique()->numerify('30########');
        $weights = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $sum = 0;

        foreach ($weights as $index => $weight) {
            $sum += (int) $base[$index] * $weight;
        }

        $checkDigit = 11 - ($sum % 11);
        $checkDigit = match ($checkDigit) {
            11 => 0,
            10 => 9,
            default => $checkDigit,
        };

        return $base.$checkDigit;
    }
}
