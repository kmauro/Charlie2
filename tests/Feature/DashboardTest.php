<?php

use App\Domains\Appointments\Enums\AppointmentStatus;
use App\Domains\Appointments\Models\Appointment;
use App\Domains\Organizations\Models\Location;
use App\Domains\Patients\Models\Patient;
use App\Domains\Professionals\Models\Professional;
use App\Domains\Users\Enums\UserStatus;
use App\Domains\Users\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('provides operational dashboard metrics through inertia props', function () {
    $this->actingAs(User::factory()->superAdmin()->create());
    $patient = Patient::factory()->create([
        'first_name' => 'Sofía',
        'last_name' => 'Benítez',
    ]);
    $professional = Professional::factory()->create();
    $professional->user->update(['name' => 'Dra. Laura Méndez']);
    $location = Location::factory()->create();
    $startsAt = now()->addHour();

    Appointment::factory()
        ->for($patient)
        ->for($professional)
        ->for($location)
        ->create([
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->clone()->addMinutes(30),
            'status' => AppointmentStatus::Confirmed,
        ]);
    Appointment::factory()
        ->for($patient)
        ->for($professional)
        ->for($location)
        ->create([
            'starts_at' => $startsAt->clone()->addHour(),
            'ends_at' => $startsAt->clone()->addMinutes(90),
            'status' => AppointmentStatus::Reserved,
        ]);
    User::factory()->create(['status' => UserStatus::PendingInvitation]);

    $this->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/views/Dashboard')
            ->has('metrics', 4, fn (Assert $metric) => $metric
                ->where('label', 'Turnos de hoy')
                ->where('value', '2')
                ->where('detail', '50% confirmados')
                ->where('icon', 'calendar')
                ->where('tone', 'blue'))
            ->has('upcomingAppointments', 2, fn (Assert $appointment) => $appointment
                ->where('time', $startsAt->format('H:i'))
                ->where('patient', 'Sofía Benítez')
                ->where('professional', 'Dra. Laura Méndez')
                ->where('specialty', $professional->specialty)
                ->where('status', 'Confirmado')
                ->where('tone', 'emerald'))
            ->has('appointmentContext', fn (Assert $context) => $context
                ->where('location', 'Todas las sedes')
                ->where('period', 'Hoy'))
            ->has('operationalStatuses', 3, fn (Assert $status) => $status
                ->where('title', 'Operación estable')
                ->where('detail', '1 sedes disponibles')
                ->where('icon', 'shield-check')
                ->where('tone', 'emerald')));
});
