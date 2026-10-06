<?php

use App\Domains\Appointments\Models\Appointment;
use App\Domains\Audits\Models\AuditLog;
use App\Domains\Patients\Models\Patient;
use App\Domains\Users\Enums\RoleCode;
use App\Domains\Users\Models\Role;
use App\Domains\Users\Models\User;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function portalPatientUser(): User
{
    return User::factory()->create([
        'role_id' => Role::query()->where('code', RoleCode::Patient->value)->sole()->getKey(),
    ]);
}

it('shows a patient only their own portal profile and upcoming appointments', function () {
    $user = portalPatientUser();
    $patient = Patient::factory()->for($user)->create();
    $ownAppointment = Appointment::factory()->for($patient)->create();
    Appointment::factory()->create();

    $this->actingAs($user)
        ->get(route('patient-portal.show'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('patients/views/Portal')
            ->where('patient.display_name', trim($patient->first_name.' '.$patient->last_name))
            ->has('upcomingAppointments', 1)
            ->where('upcomingAppointments.0.id', $ownAppointment->getKey()));
});

it('redirects a patient away from the internal dashboard to their portal', function () {
    $user = portalPatientUser();
    Patient::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('patient-portal.show'));
});

it('lets a patient update only their own contact details', function () {
    $user = portalPatientUser();
    $patient = Patient::factory()->for($user)->create([
        'phone' => '1111111111',
        'address' => 'Dirección anterior',
    ]);

    $this->actingAs($user)
        ->put(route('patient-portal.update'), [
            'phone' => '1144445555',
            'email' => 'nuevo.correo@paciente.test',
            'address' => 'Av. Siempre Viva 123',
            'coverage_name' => 'No debe actualizarse',
        ])
        ->assertRedirect()
        ->assertInertiaFlash('success');

    expect($patient->refresh()->phone)->toBe('1144445555')
        ->and($patient->email)->toBe('nuevo.correo@paciente.test')
        ->and($patient->address)->toBe('Av. Siempre Viva 123')
        ->and($patient->coverage_name)->not->toBe('No debe actualizarse')
        ->and($user->fresh()->email)->toBe('nuevo.correo@paciente.test')
        ->and(AuditLog::query()->where('event', 'patient.portal_profile_updated')->exists())->toBeTrue();
});
