<?php

use App\Domains\Users\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('provides the mocked operational dashboard through inertia props', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    $this->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/views/Dashboard')
            ->has('metrics', 4, fn (Assert $metric) => $metric
                ->where('label', 'Turnos de hoy')
                ->where('value', '128')
                ->where('detail', '82% confirmados')
                ->where('icon', 'calendar')
                ->where('tone', 'blue'))
            ->has('upcomingAppointments', 3, fn (Assert $appointment) => $appointment
                ->where('time', '08:30')
                ->where('patient', 'Sofía Benítez')
                ->where('professional', 'Dra. Laura Méndez')
                ->where('specialty', 'Cardiología')
                ->where('status', 'Confirmado')
                ->where('tone', 'emerald'))
            ->has('appointmentContext', fn (Assert $context) => $context
                ->where('location', 'Sede Palermo')
                ->where('period', 'Hoy'))
            ->has('operationalStatuses', 3, fn (Assert $status) => $status
                ->where('title', 'Operación estable')
                ->where('detail', '6 sedes disponibles')
                ->where('icon', 'shield-check')
                ->where('tone', 'emerald')));
});
