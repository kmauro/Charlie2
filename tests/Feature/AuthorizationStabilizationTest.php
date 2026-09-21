<?php

use App\Domains\Appointments\Enums\AppointmentStatus;
use App\Domains\Appointments\Models\Appointment;
use App\Domains\Organizations\Models\Location;
use App\Domains\Patients\Enums\PatientStatus;
use App\Domains\Patients\Models\Patient;
use App\Domains\Professionals\Models\Professional;
use App\Domains\Professionals\Models\ProfessionalSchedule;
use App\Domains\Users\Enums\RoleCode;
use App\Domains\Users\Enums\UserPermission;
use App\Domains\Users\Models\Role;
use App\Domains\Users\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    config()->set('services.external_clinical_record.url', 'https://clinical.example.test');
});

function stabilizationUser(RoleCode $role, array $permissions): User
{
    return User::factory()->create([
        'role_id' => Role::query()->where('code', $role->value)->value('id'),
        'permissions' => $permissions,
    ]);
}

it('requires an allowed individual permission and applies revocation immediately', function () {
    $reception = stabilizationUser(RoleCode::Reception, [
        UserPermission::ManageAppointments->value,
    ]);

    expect($reception->can('viewAny', Appointment::class))->toBeTrue();

    $reception->update(['permissions' => []]);

    expect($reception->fresh()->can('viewAny', Appointment::class))->toBeFalse()
        ->and(stabilizationUser(RoleCode::Reception, [
            UserPermission::AccessExternalClinicalRecord->value,
        ])->hasPermission(UserPermission::AccessExternalClinicalRecord))->toBeFalse()
        ->and(User::factory()->superAdmin()->create()
            ->hasPermission(UserPermission::ExportAudit))->toBeTrue();
});

it('lets professionals change only the status of their own appointments', function () {
    $professional = Professional::factory()->create();
    $ownAppointment = Appointment::factory()->for($professional)->create();
    $otherAppointment = Appointment::factory()->create();
    ProfessionalSchedule::factory()->for($professional)->create([
        'location_id' => $ownAppointment->location_id,
    ]);

    expect($professional->user->can('create', Appointment::class))->toBeFalse()
        ->and($professional->user->can('update', $ownAppointment))->toBeFalse()
        ->and($professional->user->can('changeStatus', $ownAppointment))->toBeTrue()
        ->and($professional->user->can('changeStatus', $otherAppointment))->toBeFalse()
        ->and($professional->user->can('cancel', $ownAppointment))->toBeFalse();
});

it('enforces the clinical access window and allowed appointment states', function () {
    $patient = Patient::factory()->create();
    $professional = Professional::factory()->create();
    $location = Location::factory()->create();
    $appointment = Appointment::factory()
        ->for($patient)
        ->for($professional)
        ->for($location)
        ->create([
            'starts_at' => now()->addHours(24),
            'ends_at' => now()->addHours(24)->addMinutes(30),
            'status' => AppointmentStatus::Confirmed,
        ]);

    expect($professional->user->can('accessExternalClinicalRecord', $patient))->toBeTrue();

    $appointment->update([
        'starts_at' => now()->addHours(25),
        'ends_at' => now()->addHours(25)->addMinutes(30),
    ]);
    expect($professional->user->can('accessExternalClinicalRecord', $patient))->toBeFalse();

    $appointment->update([
        'starts_at' => now()->subHours(24)->subMinutes(31),
        'ends_at' => now()->subHours(24)->subMinute(),
    ]);
    expect($professional->user->can('accessExternalClinicalRecord', $patient))->toBeFalse();

    $appointment->update([
        'starts_at' => now()->addHour(),
        'ends_at' => now()->addMinutes(90),
        'status' => AppointmentStatus::Reserved,
    ]);
    expect($professional->user->can('accessExternalClinicalRecord', $patient))->toBeFalse();

    $appointment->update(['status' => AppointmentStatus::Attended]);
    expect($professional->user->can('accessExternalClinicalRecord', $patient))->toBeTrue();

    $patient->update(['status' => PatientStatus::Blocked]);
    expect($professional->user->can('accessExternalClinicalRecord', $patient))->toBeFalse();
});

it('limits supervisor clinical access to assigned locations', function () {
    $patient = Patient::factory()->create();
    $professional = Professional::factory()->create();
    $assignedLocation = Location::factory()->create();
    $otherLocation = Location::factory()->create();
    $supervisor = stabilizationUser(RoleCode::Supervisor, [
        UserPermission::AccessExternalClinicalRecord->value,
    ]);
    $supervisor->locations()->attach($assignedLocation);
    $appointment = Appointment::factory()
        ->for($patient)
        ->for($professional)
        ->for($otherLocation)
        ->create([
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addMinutes(90),
            'status' => AppointmentStatus::Confirmed,
        ]);

    expect($supervisor->can('accessExternalClinicalRecord', $patient))->toBeFalse();

    $appointment->update(['location_id' => $assignedLocation->getKey()]);

    expect($supervisor->can('accessExternalClinicalRecord', $patient))->toBeTrue();
});
