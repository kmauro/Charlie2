<?php

namespace App\Domains\Organizations\Database\Factories;

use App\Domains\Organizations\Enums\LocationService;
use App\Domains\Organizations\Enums\OrganizationStatus;
use App\Domains\Organizations\Models\LegalEntity;
use App\Domains\Organizations\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Location> */
class LocationFactory extends Factory
{
    protected $model = Location::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'legal_entity_id' => LegalEntity::factory(),
            'name' => 'Sede '.fake()->city(),
            'code' => Str::upper(fake()->unique()->lexify('???')),
            'address' => fake()->streetAddress(),
            'neighborhood' => fake()->city(),
            'postal_code' => fake()->postcode(),
            'phone' => fake()->optional()->phoneNumber(),
            'email' => fake()->optional()->safeEmail(),
            'opening_date' => fake()->optional()->dateTimeBetween('-10 years', '+1 year'),
            'opening_time' => '08:00',
            'closing_time' => '20:00',
            'timezone' => 'America/Argentina/Buenos_Aires',
            'services' => [
                LocationService::Outpatient->value,
                LocationService::CashDesk->value,
            ],
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
}
