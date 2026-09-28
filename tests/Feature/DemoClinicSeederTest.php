<?php

use App\Domains\Appointments\Models\Appointment;
use App\Domains\Organizations\Models\LegalEntity;
use App\Domains\Organizations\Models\Location;
use App\Domains\Patients\Models\Patient;
use App\Domains\Professionals\Models\ConsultingRoom;
use App\Domains\Professionals\Models\Professional;
use App\Domains\Users\Enums\RoleCode;
use App\Domains\Users\Models\User;
use Database\Seeders\DemoClinicSeeder;

it('creates the requested demo clinic topology without duplicating it', function () {
    $this->seed(DemoClinicSeeder::class);
    $this->seed(DemoClinicSeeder::class);

    expect(LegalEntity::query()->count())->toBe(2)
        ->and(Location::query()->count())->toBe(2)
        ->and(ConsultingRoom::query()->count())->toBe(10)
        ->and(Professional::query()->count())->toBe(10)
        ->and(Patient::query()->count())->toBe(20)
        ->and(Appointment::query()->count())->toBe(4)
        ->and(User::query()->whereHas('role', fn ($query) => $query->where('code', RoleCode::Professional->value))->count())->toBe(10)
        ->and(User::query()->whereHas('role', fn ($query) => $query->whereNotIn('code', [RoleCode::SuperAdmin->value, RoleCode::Professional->value]))->count())->toBe(20);
});

it('gives demo receptionists access to both demo locations and their appointments', function () {
    $this->seed(DemoClinicSeeder::class);
    $receptionist = User::query()->where('email', 'reception.1@demo.test')->sole();

    $this->actingAs($receptionist)
        ->get(route('appointments.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->has('appointments.data', 4));

    $this->get(route('appointments.create'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('locationOptions', 2)
            ->has('professionalOptions', 10));
});
