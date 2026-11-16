<?php

use App\Domains\Audits\Data\AuditContext;
use App\Domains\Operations\Enums\AdmissionStatus;
use App\Domains\Operations\Models\EmergencyVisit;
use App\Domains\Operations\Models\Admission;
use App\Domains\Operations\Models\Bed;
use App\Domains\Operations\Models\Room;
use App\Domains\Operations\Models\Ward;
use App\Domains\Operations\Services\OperationsService;
use App\Domains\Organizations\Models\Location;
use App\Domains\Patients\Enums\PatientStatus;
use App\Domains\Patients\Models\Patient;
use App\Domains\Users\Enums\RoleCode;
use App\Domains\Users\Enums\UserPermission;
use App\Domains\Users\Models\Role;
use App\Domains\Users\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () { $this->seed(RoleSeeder::class); });

function operationsActor(RoleCode $role, UserPermission $permission): User
{
    return User::factory()->create(['role_id' => Role::query()->where('code', $role->value)->value('id'), 'permissions' => [$permission->value]]);
}

function availableOperationalBed(Location $location): Bed
{
    $suffix = (string) fake()->unique()->numberBetween(100, 999);
    $ward = Ward::query()->create(['location_id' => $location->id, 'code' => 'A'.$suffix, 'name' => 'Sector '.$suffix]);
    $room = Room::query()->create(['ward_id' => $ward->id, 'code' => $suffix]);
    return Bed::query()->create(['room_id' => $room->id, 'code' => '1']);
}

it('admits, transfers and discharges a patient while preserving bed history', function () {
    $actor = operationsActor(RoleCode::Supervisor, UserPermission::ManageAdmissions);
    $location = Location::factory()->create();
    $actor->locations()->attach($location);
    $patient = Patient::factory()->create();
    $firstBed = availableOperationalBed($location);
    $secondBed = availableOperationalBed($location);
    $service = app(OperationsService::class);
    $context = new AuditContext;

    $admission = $service->admit(['patient_id' => $patient->id, 'location_id' => $location->id, 'bed_id' => $firstBed->id], $actor, $context);

    expect($admission->status)->toBe(AdmissionStatus::Active)
        ->and($patient->refresh()->status)->toBe(PatientStatus::Inpatient);

    $service->transfer($admission, ['bed_id' => $secondBed->id, 'lock_version' => 1], $actor, $context);
    expect($admission->assignments()->count())->toBe(2)
        ->and($admission->assignments()->whereNull('ended_at')->sole()->bed_id)->toBe($secondBed->id);

    $service->discharge($admission->refresh(), 2, $actor, $context);
    expect($admission->refresh()->status)->toBe(AdmissionStatus::Discharged)
        ->and($patient->refresh()->status)->toBe(PatientStatus::Active)
        ->and($admission->assignments()->whereNull('ended_at')->exists())->toBeFalse();
});

it('rejects assigning an already occupied bed', function () {
    $actor = operationsActor(RoleCode::Supervisor, UserPermission::ManageAdmissions);
    $location = Location::factory()->create();
    $actor->locations()->attach($location);
    $bed = availableOperationalBed($location);
    $service = app(OperationsService::class);
    $context = new AuditContext;
    $service->admit(['patient_id' => Patient::factory()->create()->id, 'location_id' => $location->id, 'bed_id' => $bed->id], $actor, $context);

    expect(fn () => $service->admit(['patient_id' => Patient::factory()->create()->id, 'location_id' => $location->id, 'bed_id' => $bed->id], $actor, $context))
        ->toThrow(ValidationException::class);
});

it('allows guardia and enfermeria roles to access both operational modules', function (RoleCode $role) {
    $actor = operationsActor($role, UserPermission::ManageEmergencyVisits);

    $actor->permissions = [
        UserPermission::ManageEmergencyVisits->value,
        UserPermission::ViewInpatientCensus->value,
    ];
    $actor->save();

    expect($actor->can('viewAny', EmergencyVisit::class))->toBeTrue()
        ->and($actor->can('viewAny', Admission::class))->toBeTrue();
})->with([
    'guardia' => RoleCode::Emergency,
    'enfermeria' => RoleCode::Nursing,
]);
