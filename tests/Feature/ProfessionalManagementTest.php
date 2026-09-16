<?php

use App\Domains\Organizations\Enums\OrganizationStatus;
use App\Domains\Organizations\Models\Location;
use App\Domains\Professionals\Enums\OfficeAssignmentType;
use App\Domains\Professionals\Enums\ProfessionalStatus;
use App\Domains\Professionals\Models\Professional;
use App\Domains\Professionals\Models\ProfessionalSchedule;
use App\Domains\Professionals\Repositories\Contracts\ProfessionalRepository;
use App\Domains\Users\Database\Seeders\RoleSeeder;
use App\Domains\Users\Enums\RoleCode;
use App\Domains\Users\Enums\UserStatus;
use App\Domains\Users\Models\Role;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\mock;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function professionalsSuperAdmin(): User
{
    return User::factory()->superAdmin()->create();
}

/**
 * @return array{central: Location, north: Location}
 */
function professionalScheduleLocations(): array
{
    return [
        'central' => Location::factory()->create([
            'name' => 'Sede Central',
            'code' => 'PRO-CENTRAL',
        ]),
        'north' => Location::factory()->create([
            'name' => 'Sede Norte',
            'code' => 'PRO-NORTE',
        ]),
    ];
}

/**
 * @return array<string, mixed>
 */
function validProfessionalPayload(array $overrides = [], ?array $locations = null): array
{
    $locations ??= professionalScheduleLocations();

    return [
        'name' => 'Dra. Cecilia Grierson',
        'email' => 'cecilia.grierson@clinica.test',
        'document_number' => '12345678',
        'phone' => '1144445555',
        'specialty' => 'Clínica médica',
        'registration_type' => 'M.N.',
        'registration_number' => 'MN-45678',
        'appointment_duration_minutes' => 30,
        'office_assignment_type' => OfficeAssignmentType::Fixed->value,
        'fixed_office' => 'Consultorio 12',
        'schedules' => [
            [
                'location_id' => $locations['central']->getKey(),
                'weekday' => 1,
                'start_time' => '09:00',
                'end_time' => '13:00',
            ],
            [
                'location_id' => $locations['north']->getKey(),
                'weekday' => 3,
                'start_time' => '14:00',
                'end_time' => '18:00',
            ],
        ],
        'expires_in_hours' => 72,
        ...$overrides,
    ];
}

/**
 * @return array<string, mixed>
 */
function validProfessionalUpdatePayload(
    Professional $professional,
    array $overrides = [],
    ?array $locations = null,
): array
{
    $professional->loadMissing('user');
    $locations ??= professionalScheduleLocations();

    return [
        'name' => $professional->user->name,
        'email' => $professional->user->email,
        'document_number' => $professional->document_number,
        'phone' => $professional->phone,
        'specialty' => $professional->specialty,
        'registration_type' => $professional->registration_type,
        'registration_number' => $professional->registration_number,
        'appointment_duration_minutes' => $professional->appointment_duration_minutes,
        'status' => $professional->status->value,
        'office_assignment_type' => $professional->office_assignment_type->value,
        'fixed_office' => $professional->fixed_office,
        'schedules' => [
            [
                'location_id' => $locations['central']->getKey(),
                'weekday' => 1,
                'start_time' => '09:00',
                'end_time' => '13:00',
            ],
        ],
        ...$overrides,
    ];
}

it('allows only superadmins to access every professional management action', function () {
    $actor = User::factory()->create();
    $professional = Professional::factory()->create();

    $this->actingAs($actor);

    $this->get(route('professionals.index'))->assertForbidden();
    $this->get(route('professionals.create'))->assertForbidden();
    $this->post(route('professionals.store'), [])->assertForbidden();
    $this->get(route('professionals.edit', $professional))->assertForbidden();
    $this->put(route('professionals.update', $professional), [])->assertForbidden();
    $this->delete(route('professionals.destroy', $professional))->assertForbidden();
    $this->patch(route('professionals.restore', $professional))->assertForbidden();
});

it('never authorizes permanent deletion of professional records', function () {
    $actor = professionalsSuperAdmin();
    $professional = Professional::factory()->create();

    expect($actor->can('forceDelete', $professional))->toBeFalse();
});

it('prevents a professional profile from being cascaded by a direct user deletion', function () {
    $professional = Professional::factory()->create();

    expect(fn () => $professional->user->delete())
        ->toThrow(QueryException::class);

    $this->assertDatabaseHas('users', [
        'id' => $professional->user_id,
    ]);
    $this->assertDatabaseHas('professionals', [
        'id' => $professional->getKey(),
    ]);
});

it('renders professional screens with paginator summaries schedules and form options', function () {
    $actor = professionalsSuperAdmin();
    $location = Location::factory()->create([
        'name' => 'Sede Central',
        'code' => 'PRO-CENTRAL',
    ]);
    Location::factory()->create([
        'name' => 'Sede Inactiva',
        'code' => 'PRO-INACTIVA',
        'status' => OrganizationStatus::Inactive,
    ]);
    $professional = Professional::factory()->create([
        'specialty' => 'Cardiología',
    ]);
    ProfessionalSchedule::factory()
        ->for($professional)
        ->forLocation($location)
        ->create([
            'location_name' => 'Nombre legado desactualizado',
            'weekday' => 2,
            'start_time' => '08:00',
            'end_time' => '12:00',
        ]);

    $this->actingAs($actor)
        ->get(route('professionals.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('professionals/views/Index')
            ->has('professionals.data', 1, fn (Assert $item) => $item
                ->where('id', $professional->getKey())
                ->where('specialty', 'Cardiología')
                ->has('schedules', 1, fn (Assert $schedule) => $schedule
                    ->where('location_id', $location->getKey())
                    ->where('location_name', 'Sede Central')
                    ->where('weekday', 2)
                    ->etc())
                ->has('status')
                ->has('office_assignment_type')
                ->etc())
            ->has('filters')
            ->has('summaries', fn (Assert $summaries) => $summaries
                ->where('total', 1)
                ->where('active', 1)
                ->where('inactive', 0)
                ->where('on_leave', 0)
                ->where('invitation_pending', 0))
            ->has('statusOptions', count(ProfessionalStatus::cases()))
            ->has('officeAssignmentTypes', count(OfficeAssignmentType::cases())));

    $this->get(route('professionals.create'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('professionals/views/Create')
            ->has('statuses', count(ProfessionalStatus::cases()))
            ->has('statusOptions', count(ProfessionalStatus::cases()))
            ->has('officeAssignmentTypes', count(OfficeAssignmentType::cases()))
            ->has('locationOptions', 1, fn (Assert $option) => $option
                ->where('value', $location->getKey())
                ->where('label', 'Sede Central')
                ->where('code', 'PRO-CENTRAL')));

    $this->get(route('professionals.edit', $professional))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('professionals/views/Edit')
            ->where('professional.id', $professional->getKey())
            ->has('professional.schedules', 1, fn (Assert $schedule) => $schedule
                ->where('location_id', $location->getKey())
                ->where('location_name', 'Sede Central')
                ->etc())
            ->has('statuses', count(ProfessionalStatus::cases()))
            ->has('statusOptions', count(ProfessionalStatus::cases()))
            ->has('officeAssignmentTypes', count(OfficeAssignmentType::cases()))
            ->has('locationOptions', 1, fn (Assert $option) => $option
                ->where('value', $location->getKey())
                ->where('label', 'Sede Central')
                ->where('code', 'PRO-CENTRAL')));
});

it('creates a professional user invitation profile and schedules atomically', function () {
    $actor = professionalsSuperAdmin();
    $locations = professionalScheduleLocations();

    $this->actingAs($actor)
        ->post(route('professionals.store'), validProfessionalPayload([], $locations))
        ->assertRedirect(route('professionals.index'))
        ->assertInertiaFlash('invitationUrl');

    $user = User::query()
        ->with('role')
        ->where('email', 'cecilia.grierson@clinica.test')
        ->sole();
    $professional = Professional::query()
        ->where('user_id', $user->getKey())
        ->sole();
    $professional->load('schedules');

    expect($user->role?->code)
        ->toBe(RoleCode::Professional)
        ->and($user->status)->toBe(UserStatus::PendingInvitation)
        ->and($professional->document_number)->toBe('12345678')
        ->and($professional->registration_type)->toBe('M.N.')
        ->and($professional->registration_number)->toBe('MN-45678')
        ->and($professional->status)->toBe(ProfessionalStatus::Active)
        ->and($professional->office_assignment_type)->toBe(OfficeAssignmentType::Fixed)
        ->and($professional->fixed_office)->toBe('Consultorio 12')
        ->and($professional->schedules)->toHaveCount(2)
        ->and($professional->schedules->pluck('location_id')->all())->toBe([
            $locations['central']->getKey(),
            $locations['north']->getKey(),
        ])
        ->and($professional->schedules->pluck('location_name')->all())->toBe([
            'Sede Central',
            'Sede Norte',
        ]);
});

it('rolls back the invited user when creating the professional profile fails', function () {
    $actor = professionalsSuperAdmin();

    mock(ProfessionalRepository::class)
        ->shouldReceive('create')
        ->once()
        ->andThrow(new RuntimeException('forced professional persistence failure'));

    $this->withoutExceptionHandling();

    expect(fn () => $this->actingAs($actor)->post(
        route('professionals.store'),
        validProfessionalPayload(['email' => 'rollback@clinica.test']),
    ))->toThrow(RuntimeException::class, 'forced professional persistence failure');

    $this->assertDatabaseMissing('users', [
        'email' => 'rollback@clinica.test',
    ]);
    expect(Professional::query()->count())->toBe(0);
});

it('validates unique DNI and professional registration', function () {
    $actor = professionalsSuperAdmin();
    $existing = Professional::factory()->create([
        'document_number' => '23456789',
        'registration_type' => 'M.P.',
        'registration_number' => 'MP-11223',
    ]);

    $this->actingAs($actor)
        ->post(route('professionals.store'), validProfessionalPayload([
            'email' => 'duplicado@clinica.test',
            'document_number' => $existing->document_number,
            'registration_type' => $existing->registration_type,
            'registration_number' => $existing->registration_number,
        ]))
        ->assertSessionHasErrors([
            'document_number',
            'registration_number',
        ]);

    $this->assertDatabaseMissing('users', [
        'email' => 'duplicado@clinica.test',
    ]);
});

it('validates the DNI format and rejects reversed or overlapping schedules', function () {
    $actor = professionalsSuperAdmin();
    $locations = professionalScheduleLocations();

    $this->actingAs($actor)
        ->post(route('professionals.store'), validProfessionalPayload([
            'email' => 'horarios.invalidos@clinica.test',
            'document_number' => '12ABC',
            'schedules' => [
                [
                    'location_id' => $locations['central']->getKey(),
                    'weekday' => 1,
                    'start_time' => '12:00',
                    'end_time' => '09:00',
                ],
                [
                    'location_id' => $locations['north']->getKey(),
                    'weekday' => 3,
                    'start_time' => '09:00',
                    'end_time' => '12:00',
                ],
                [
                    'location_id' => $locations['north']->getKey(),
                    'weekday' => 3,
                    'start_time' => '11:00',
                    'end_time' => '14:00',
                ],
            ],
        ], $locations))
        ->assertSessionHasErrors([
            'document_number',
            'schedules.0.end_time',
            'schedules.2.start_time',
        ]);

    $this->assertDatabaseMissing('users', [
        'email' => 'horarios.invalidos@clinica.test',
    ]);
});

it('updates the professional user profile and replaces their schedules', function () {
    $actor = professionalsSuperAdmin();
    $locations = professionalScheduleLocations();
    $oldLocation = Location::factory()->create([
        'name' => 'Sede anterior',
        'code' => 'PRO-ANTERIOR',
    ]);
    $southLocation = Location::factory()->create([
        'name' => 'Sede Sur',
        'code' => 'PRO-SUR',
    ]);
    $professional = Professional::factory()->create();
    $oldSchedule = ProfessionalSchedule::factory()
        ->for($professional)
        ->forLocation($oldLocation)
        ->create([
            'weekday' => 5,
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);

    $this->actingAs($actor)
        ->put(route('professionals.update', $professional), validProfessionalUpdatePayload(
            $professional,
            [
                'name' => '  Dr. Ramón Carrillo  ',
                'email' => 'RAMON.CARRILLO@CLINICA.TEST',
                'specialty' => 'Medicina sanitaria',
                'appointment_duration_minutes' => 45,
                'status' => ProfessionalStatus::OnLeave->value,
                'office_assignment_type' => OfficeAssignmentType::Availability->value,
                'fixed_office' => null,
                'schedules' => [
                    [
                        'location_id' => $locations['central']->getKey(),
                        'weekday' => 2,
                        'start_time' => '08:30',
                        'end_time' => '12:30',
                    ],
                    [
                        'location_id' => $southLocation->getKey(),
                        'weekday' => 4,
                        'start_time' => '14:00',
                        'end_time' => '18:00',
                    ],
                ],
            ],
            $locations,
        ))
        ->assertRedirect(route('professionals.index'));

    $professional->refresh()->load(['user', 'schedules']);

    expect($professional->user->name)
        ->toBe('Dr. Ramón Carrillo')
        ->and($professional->user->email)->toBe('ramon.carrillo@clinica.test')
        ->and($professional->user->status)->toBe(UserStatus::Active)
        ->and($professional->specialty)->toBe('Medicina sanitaria')
        ->and($professional->appointment_duration_minutes)->toBe(45)
        ->and($professional->status)->toBe(ProfessionalStatus::OnLeave)
        ->and($professional->office_assignment_type)->toBe(OfficeAssignmentType::Availability)
        ->and($professional->fixed_office)->toBeNull()
        ->and($professional->schedules)->toHaveCount(2)
        ->and($professional->schedules->pluck('location_id')->all())
        ->toBe([$locations['central']->getKey(), $southLocation->getKey()])
        ->and($professional->schedules->pluck('location_name')->all())
        ->toBe(['Sede Central', 'Sede Sur'])
        ->and(ProfessionalSchedule::query()->whereKey($oldSchedule->getKey())->exists())
        ->toBeFalse();
});

it('inactivates and restores a professional together with their user account', function () {
    $actor = professionalsSuperAdmin();
    $professional = Professional::factory()->create();

    $this->actingAs($actor)
        ->delete(route('professionals.destroy', $professional))
        ->assertRedirect()
        ->assertInertiaFlash('success');

    $professional->refresh()->load('user');

    expect($professional->status)
        ->toBe(ProfessionalStatus::Inactive)
        ->and($professional->user->status)->toBe(UserStatus::Suspended);

    $this->patch(route('professionals.restore', $professional))
        ->assertRedirect()
        ->assertInertiaFlash('success');

    $professional->refresh()->load('user');

    expect($professional->status)
        ->toBe(ProfessionalStatus::Active)
        ->and($professional->user->status)->toBe(UserStatus::Active);
});

it('issues a fresh invitation when restoring a professional who never accepted theirs', function () {
    $actor = professionalsSuperAdmin();
    $professionalRole = Role::query()
        ->where('code', RoleCode::Professional->value)
        ->sole();
    $user = User::factory()->pendingInvitation()->create([
        'role_id' => $professionalRole->getKey(),
    ]);
    $professional = Professional::factory()->for($user)->create();

    $this->actingAs($actor)
        ->delete(route('professionals.destroy', $professional))
        ->assertRedirect()
        ->assertInertiaFlash('success');

    $user->refresh();

    expect($user->status)
        ->toBe(UserStatus::Suspended)
        ->and($user->invitation_token_hash)->toBeNull()
        ->and($user->invitation_expires_at)->toBeNull();

    $this->patch(route('professionals.restore', $professional))
        ->assertRedirect()
        ->assertInertiaFlash('success')
        ->assertInertiaFlash('invitationUrl');

    $professional->refresh();
    $user->refresh();

    expect($professional->status)
        ->toBe(ProfessionalStatus::Active)
        ->and($user->status)->toBe(UserStatus::PendingInvitation)
        ->and($user->invitation_token_hash)->not->toBeNull()
        ->and($user->invitation_expires_at)->not->toBeNull();
});
