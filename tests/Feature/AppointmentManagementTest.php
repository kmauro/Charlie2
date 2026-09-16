<?php

use App\Domains\Appointments\Enums\AppointmentStatus;
use App\Domains\Appointments\Enums\AppointmentType;
use App\Domains\Appointments\Models\Appointment;
use App\Domains\Audits\Models\AuditLog;
use App\Domains\Organizations\Enums\OrganizationStatus;
use App\Domains\Organizations\Models\Location;
use App\Domains\Patients\Enums\PatientStatus;
use App\Domains\Patients\Models\Patient;
use App\Domains\Professionals\Enums\ProfessionalStatus;
use App\Domains\Professionals\Models\Professional;
use App\Domains\Professionals\Models\ProfessionalSchedule;
use App\Domains\Users\Database\Seeders\RoleSeeder;
use App\Domains\Users\Enums\RoleCode;
use App\Domains\Users\Enums\UserPermission;
use App\Domains\Users\Models\Role;
use App\Domains\Users\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function appointmentsActor(RoleCode $role): User
{
    return User::factory()->create([
        'role_id' => Role::query()->where('code', $role->value)->value('id'),
        'permissions' => UserPermission::ManageAppointments->isAllowedFor($role)
            ? [UserPermission::ManageAppointments->value]
            : [],
    ]);
}

function grantAppointmentLocation(User $actor, Location $location): void
{
    if (! $actor->hasRole(RoleCode::SuperAdmin)) {
        $actor->locations()->syncWithoutDetaching([$location->getKey()]);
    }
}

/** @return array{patient: Patient, professional: Professional, location: Location, starts_at: CarbonInterface} */
function appointmentSchedulingContext(
    int $durationMinutes = 30,
    ?CarbonInterface $startsAt = null,
): array {
    $startsAt ??= now()->addDays(7)->startOfDay()->addHours(10);
    $patient = Patient::factory()->create();
    $professional = Professional::factory()->create([
        'appointment_duration_minutes' => $durationMinutes,
        'status' => ProfessionalStatus::Active,
    ]);
    $location = Location::factory()->create([
        'name' => 'Sede Central',
        'code' => 'CENTRAL',
        'status' => OrganizationStatus::Active,
    ]);

    ProfessionalSchedule::factory()->for($professional)->create([
        'location_id' => $location->getKey(),
        'location_name' => '  SEDE   central ',
        'weekday' => $startsAt->dayOfWeekIso,
        'start_time' => '08:00',
        'end_time' => '18:00',
    ]);

    return [
        'patient' => $patient,
        'professional' => $professional,
        'location' => $location,
        'starts_at' => $startsAt,
    ];
}

/** @param array<string, mixed> $overrides */
function validAppointmentPayload(array $context, array $overrides = []): array
{
    return [
        'patient_id' => $context['patient']->getKey(),
        'professional_id' => $context['professional']->getKey(),
        'location_id' => $context['location']->getKey(),
        'starts_at' => $context['starts_at']->format('Y-m-d\TH:i'),
        'type' => AppointmentType::InPerson->value,
        'office' => 'Consultorio 4',
        'reason' => 'Control programado',
        ...$overrides,
    ];
}

it('applies the appointment role matrix and never permits deletion', function () {
    $appointment = Appointment::factory()->create();

    foreach ([
        RoleCode::SuperAdmin,
        RoleCode::Administrator,
        RoleCode::Supervisor,
        RoleCode::Reception,
    ] as $role) {
        $actor = appointmentsActor($role);
        grantAppointmentLocation($actor, $appointment->location);

        expect($actor->can('viewAny', Appointment::class))->toBeTrue()
            ->and($actor->can('create', Appointment::class))->toBeTrue()
            ->and($actor->can('update', $appointment))->toBeTrue()
            ->and($actor->can('cancel', $appointment))->toBeTrue()
            ->and($actor->can('delete', $appointment))->toBeFalse()
            ->and($actor->can('forceDelete', $appointment))->toBeFalse();
    }

    foreach ([
        RoleCode::Nursing,
        RoleCode::Emergency,
        RoleCode::Manager,
    ] as $role) {
        $actor = appointmentsActor($role);

        expect($actor->can('viewAny', Appointment::class))->toBeFalse()
            ->and($actor->can('create', Appointment::class))->toBeFalse()
            ->and($actor->can('update', $appointment))->toBeFalse();
    }

    $professional = Professional::factory()->create();

    expect($professional->user->can('viewAny', Appointment::class))->toBeTrue()
        ->and($professional->user->can('create', Appointment::class))->toBeFalse()
        ->and($professional->user->can('update', $appointment))->toBeFalse();

    foreach ([
        RoleCode::Accounting,
        RoleCode::Sales,
        RoleCode::Auditor,
        RoleCode::Patient,
    ] as $role) {
        expect(appointmentsActor($role)->can('viewAny', Appointment::class))
            ->toBeFalse();
    }
});

it('renders index and form contracts from persisted references', function () {
    $actor = appointmentsActor(RoleCode::Administrator);
    $context = appointmentSchedulingContext();
    grantAppointmentLocation($actor, $context['location']);
    $appointment = Appointment::factory()->create([
        'patient_id' => $context['patient'],
        'professional_id' => $context['professional'],
        'location_id' => $context['location'],
        'starts_at' => $context['starts_at'],
        'ends_at' => $context['starts_at']->clone()->addMinutes(30),
    ]);

    $this->actingAs($actor)
        ->get(route('appointments.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('appointments/views/Index')
            ->has('appointments.data', 1, fn (Assert $item) => $item
                ->where('id', $appointment->getKey())
                ->where('patient.id', $context['patient']->getKey())
                ->where('professional.id', $context['professional']->getKey())
                ->where('location.id', $context['location']->getKey())
                ->where('is_overbook', false)
                ->has('status')
                ->has('type')
                ->has('permissions')
                ->has('urls')
                ->etc())
            ->has('filters')
            ->has('summaries')
            ->has('statusOptions', count(AppointmentStatus::cases()))
            ->has('typeOptions', count(AppointmentType::cases()))
            ->has('locationOptions', 1)
            ->has('professionalOptions', 1)
            ->where('permissions.create', true));

    $this->get(route('appointments.create'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('appointments/views/Create')
            ->has('patientOptions', 1)
            ->has('professionalOptions', 1, fn (Assert $professional) => $professional
                ->where('value', $context['professional']->getKey())
                ->where('duration_minutes', 30)
                ->has('schedules', 1, fn (Assert $schedule) => $schedule
                    ->where('location_id', $context['location']->getKey())
                    ->etc())
                ->etc())
            ->has('locationOptions', 1)
            ->has('typeOptions', count(AppointmentType::cases()))
            ->has('statusOptions', count(AppointmentStatus::cases()))
            ->has('urls'));
});

it('creates using the professional duration snapshot', function () {
    $actor = appointmentsActor(RoleCode::Reception);
    $context = appointmentSchedulingContext(45);
    grantAppointmentLocation($actor, $context['location']);

    $this->actingAs($actor)
        ->post(route('appointments.store'), validAppointmentPayload($context))
        ->assertRedirect(route('appointments.index'))
        ->assertInertiaFlash('success');

    $appointment = Appointment::query()->sole();

    expect($appointment->duration_minutes)->toBe(45)
        ->and($appointment->ends_at->equalTo(
            $context['starts_at']->clone()->addMinutes(45),
        ))->toBeTrue()
        ->and($appointment->status)->toBe(AppointmentStatus::Reserved)
        ->and($appointment->is_overbook)->toBeFalse()
        ->and($appointment->lock_version)->toBe(1)
        ->and(AuditLog::query()->where('event', 'appointment.created')->count())
        ->toBe(1);
});

it('keeps the professional schedule linked when a location is renamed', function () {
    $actor = appointmentsActor(RoleCode::Reception);
    $context = appointmentSchedulingContext();
    grantAppointmentLocation($actor, $context['location']);
    $context['location']->update(['name' => 'Sede Central Renovada']);

    $this->actingAs($actor)
        ->post(route('appointments.store'), validAppointmentPayload($context))
        ->assertRedirect(route('appointments.index'));

    expect(Appointment::query()->count())->toBe(1);
});

it('keeps normalized-name fallback for legacy professional schedules', function () {
    $actor = appointmentsActor(RoleCode::Reception);
    $context = appointmentSchedulingContext();
    grantAppointmentLocation($actor, $context['location']);
    ProfessionalSchedule::query()->update(['location_id' => null]);

    $this->actingAs($actor)
        ->post(route('appointments.store'), validAppointmentPayload($context))
        ->assertRedirect(route('appointments.index'));

    expect(Appointment::query()->count())->toBe(1);
});

it('rejects overbook and server-calculated fields from clients', function () {
    $actor = appointmentsActor(RoleCode::Reception);
    $context = appointmentSchedulingContext();
    grantAppointmentLocation($actor, $context['location']);

    $this->actingAs($actor)
        ->post(route('appointments.store'), validAppointmentPayload($context, [
            'is_overbook' => true,
            'duration_minutes' => 5,
            'ends_at' => $context['starts_at']->clone()->addMinutes(5),
            'status' => AppointmentStatus::Confirmed->value,
        ]))
        ->assertSessionHasErrors([
            'is_overbook',
            'duration_minutes',
            'ends_at',
            'status',
        ]);

    expect(Appointment::query()->count())->toBe(0);

    expect(fn () => Appointment::factory()->create(['is_overbook' => true]))
        ->toThrow(LogicException::class, 'Los sobreturnos todavía no están habilitados.');
});

it('requires eligible references and a matching professional schedule', function () {
    $actor = appointmentsActor(RoleCode::Administrator);
    $context = appointmentSchedulingContext();
    grantAppointmentLocation($actor, $context['location']);

    $context['patient']->update(['status' => PatientStatus::Blocked]);
    $this->actingAs($actor)
        ->post(route('appointments.store'), validAppointmentPayload($context))
        ->assertSessionHasErrors('patient_id');

    $context['patient']->update(['status' => PatientStatus::Active]);
    $context['professional']->update(['status' => ProfessionalStatus::Inactive]);
    $this->post(route('appointments.store'), validAppointmentPayload($context))
        ->assertSessionHasErrors('professional_id');

    $context['professional']->update(['status' => ProfessionalStatus::Active]);
    $context['location']->update(['status' => OrganizationStatus::Inactive]);
    $this->post(route('appointments.store'), validAppointmentPayload($context))
        ->assertSessionHasErrors('location_id');

    $context['location']->update(['status' => OrganizationStatus::Active]);
    ProfessionalSchedule::query()->update([
        'location_id' => null,
        'location_name' => 'Otra sede',
    ]);
    $this->post(route('appointments.store'), validAppointmentPayload($context))
        ->assertSessionHasErrors('starts_at');

    expect(Appointment::query()->count())->toBe(0);
});

it('requires the complete appointment window to fit location operating hours', function () {
    $actor = appointmentsActor(RoleCode::Reception);
    $context = appointmentSchedulingContext(30);
    grantAppointmentLocation($actor, $context['location']);
    $context['location']->update([
        'opening_time' => '10:15',
        'closing_time' => '20:00',
    ]);

    $this->actingAs($actor)
        ->post(route('appointments.store'), validAppointmentPayload($context))
        ->assertSessionHasErrors('starts_at');

    $context['location']->update([
        'opening_time' => '08:00',
        'closing_time' => '10:15',
    ]);

    $this->post(route('appointments.store'), validAppointmentPayload($context))
        ->assertSessionHasErrors('starts_at');

    expect(Appointment::query()->count())->toBe(0);
});

it('blocks overlaps for the professional patient and office', function () {
    $actor = appointmentsActor(RoleCode::Reception);
    $context = appointmentSchedulingContext();
    grantAppointmentLocation($actor, $context['location']);
    Appointment::factory()->create([
        'patient_id' => $context['patient'],
        'professional_id' => $context['professional'],
        'location_id' => $context['location'],
        'starts_at' => $context['starts_at'],
        'ends_at' => $context['starts_at']->clone()->addMinutes(30),
        'office' => 'Consultorio 4',
        'status' => AppointmentStatus::Confirmed,
    ]);

    $otherPatient = Patient::factory()->create();
    $this->actingAs($actor)
        ->post(route('appointments.store'), validAppointmentPayload($context, [
            'patient_id' => $otherPatient->getKey(),
            'starts_at' => $context['starts_at']->clone()->addMinutes(15)->format('Y-m-d\TH:i'),
            'office' => 'Consultorio 8',
        ]))
        ->assertSessionHasErrors('starts_at');

    $otherProfessional = Professional::factory()->create([
        'status' => ProfessionalStatus::Active,
        'appointment_duration_minutes' => 30,
    ]);
    ProfessionalSchedule::factory()->for($otherProfessional)->create([
        'location_name' => $context['location']->name,
        'weekday' => $context['starts_at']->dayOfWeekIso,
        'start_time' => '08:00',
        'end_time' => '18:00',
    ]);

    $this->post(route('appointments.store'), validAppointmentPayload($context, [
        'professional_id' => $otherProfessional->getKey(),
        'starts_at' => $context['starts_at']->clone()->addMinutes(15)->format('Y-m-d\TH:i'),
        'office' => 'Consultorio 8',
    ]))->assertSessionHasErrors('starts_at');

    $thirdPatient = Patient::factory()->create();
    $thirdProfessional = Professional::factory()->create([
        'status' => ProfessionalStatus::Active,
        'appointment_duration_minutes' => 30,
    ]);
    ProfessionalSchedule::factory()->for($thirdProfessional)->create([
        'location_name' => $context['location']->name,
        'weekday' => $context['starts_at']->dayOfWeekIso,
        'start_time' => '08:00',
        'end_time' => '18:00',
    ]);

    $this->post(route('appointments.store'), validAppointmentPayload($context, [
        'patient_id' => $thirdPatient->getKey(),
        'professional_id' => $thirdProfessional->getKey(),
        'starts_at' => $context['starts_at']->clone()->addMinutes(15)->format('Y-m-d\TH:i'),
    ]))->assertSessionHasErrors('office');

    expect(Appointment::query()->count())->toBe(1);
});

it('allows adjacent appointments because they do not overlap', function () {
    $actor = appointmentsActor(RoleCode::Reception);
    $context = appointmentSchedulingContext();
    grantAppointmentLocation($actor, $context['location']);
    Appointment::factory()->create([
        'patient_id' => Patient::factory(),
        'professional_id' => $context['professional'],
        'location_id' => $context['location'],
        'starts_at' => $context['starts_at'],
        'ends_at' => $context['starts_at']->clone()->addMinutes(30),
        'office' => 'Consultorio 4',
    ]);

    $this->actingAs($actor)
        ->post(route('appointments.store'), validAppointmentPayload($context, [
            'starts_at' => $context['starts_at']->clone()->addMinutes(30)->format('Y-m-d\TH:i'),
        ]))
        ->assertRedirect(route('appointments.index'));

    expect(Appointment::query()->count())->toBe(2);
});

it('updates atomically and rejects a stale lock version', function () {
    $actor = appointmentsActor(RoleCode::Administrator);
    $context = appointmentSchedulingContext(45);
    grantAppointmentLocation($actor, $context['location']);
    $appointment = Appointment::factory()->create([
        'patient_id' => $context['patient'],
        'professional_id' => $context['professional'],
        'location_id' => $context['location'],
        'starts_at' => $context['starts_at'],
        'ends_at' => $context['starts_at']->clone()->addMinutes(45),
        'duration_minutes' => 45,
    ]);
    $newStart = $context['starts_at']->clone()->addHour();

    $this->actingAs($actor)
        ->put(route('appointments.update', $appointment), validAppointmentPayload($context, [
            'starts_at' => $newStart->format('Y-m-d\TH:i'),
            'lock_version' => 1,
        ]))
        ->assertRedirect(route('appointments.index'));

    $appointment->refresh();

    expect($appointment->starts_at->equalTo($newStart))->toBeTrue()
        ->and($appointment->ends_at->equalTo($newStart->clone()->addMinutes(45)))->toBeTrue()
        ->and($appointment->lock_version)->toBe(2);

    $this->put(route('appointments.update', $appointment), validAppointmentPayload($context, [
        'starts_at' => $newStart->clone()->addHour()->format('Y-m-d\TH:i'),
        'lock_version' => 1,
    ]))->assertSessionHasErrors('lock_version');
});

it('changes status only through valid workflow transitions', function () {
    $actor = appointmentsActor(RoleCode::Supervisor);
    $appointment = Appointment::factory()->create([
        'status' => AppointmentStatus::Reserved,
    ]);
    grantAppointmentLocation($actor, $appointment->location);

    $this->actingAs($actor)
        ->patch(route('appointments.status', $appointment), [
            'status' => AppointmentStatus::Confirmed->value,
            'lock_version' => 1,
        ])
        ->assertRedirect();

    $appointment->refresh();
    expect($appointment->status)->toBe(AppointmentStatus::Confirmed)
        ->and($appointment->lock_version)->toBe(2)
        ->and(AuditLog::query()->where('event', 'appointment.status_changed')->sole()->before_values)->toBe([])
        ->and(AuditLog::query()->where('event', 'appointment.status_changed')->sole()->after_values)->toBe([]);

    $this->patch(route('appointments.status', $appointment), [
        'status' => AppointmentStatus::Cancelled->value,
        'lock_version' => 2,
    ])->assertSessionHasErrors('status');
});

it('cancels with at least 24 hours notice and preserves actor traceability', function () {
    $actor = appointmentsActor(RoleCode::Reception);
    $appointment = Appointment::factory()->create([
        'starts_at' => now()->addHours(48),
        'ends_at' => now()->addHours(48)->addMinutes(30),
    ]);
    grantAppointmentLocation($actor, $appointment->location);

    $this->actingAs($actor)
        ->patch(route('appointments.cancel', $appointment), [
            'cancellation_reason' => '  El paciente informó que no podrá concurrir. ',
            'lock_version' => 1,
        ])
        ->assertRedirect();

    $appointment->refresh();

    expect($appointment->status)->toBe(AppointmentStatus::Cancelled)
        ->and($appointment->cancellation_reason)
        ->toBe('El paciente informó que no podrá concurrir.')
        ->and($appointment->cancelled_at)->not->toBeNull()
        ->and($appointment->cancelled_by)->toBe($actor->getKey())
        ->and($appointment->lock_version)->toBe(2);

    $audit = AuditLog::query()->where('event', 'appointment.cancelled')->sole();
    expect($audit->after_values)->not->toHaveKey('cancellation_reason')
        ->and($audit->after_values['has_cancellation_reason'])->toBeTrue();
});

it('blocks cancellation with less than 24 hours notice', function () {
    $actor = appointmentsActor(RoleCode::Administrator);
    $appointment = Appointment::factory()->create([
        'starts_at' => now()->addHours(23),
        'ends_at' => now()->addHours(23)->addMinutes(30),
    ]);
    grantAppointmentLocation($actor, $appointment->location);

    $this->actingAs($actor)
        ->patch(route('appointments.cancel', $appointment), [
            'cancellation_reason' => 'Cambio de agenda.',
            'lock_version' => 1,
        ])
        ->assertSessionHasErrors('cancellation_reason');

    expect($appointment->refresh()->status)->toBe(AppointmentStatus::Reserved)
        ->and($appointment->cancelled_at)->toBeNull();
});

it('restricts professional listings to their own appointments', function () {
    $context = appointmentSchedulingContext();
    $ownAppointment = Appointment::factory()->create([
        'patient_id' => $context['patient'],
        'professional_id' => $context['professional'],
        'location_id' => $context['location'],
    ]);
    Appointment::factory()->create();

    $this->actingAs($context['professional']->user)
        ->get(route('appointments.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('appointments.data', 1)
            ->where('appointments.data.0.id', $ownAppointment->getKey())
            ->where('permissions.create', false));
});

it('does not expose a permanent deletion endpoint', function () {
    $appointment = Appointment::factory()->create();
    $routeNames = collect(Route::getRoutes())
        ->map(fn ($route): ?string => $route->getName())
        ->filter(fn (?string $name): bool => str_starts_with((string) $name, 'appointments.'))
        ->sort()
        ->values()
        ->all();

    expect($routeNames)->toBe([
        'appointments.cancel',
        'appointments.create',
        'appointments.edit',
        'appointments.index',
        'appointments.status',
        'appointments.store',
        'appointments.update',
    ])->and(fn () => $appointment->delete())
        ->toThrow(LogicException::class);
});
