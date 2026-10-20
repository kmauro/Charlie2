<?php

use App\Domains\Appointments\Models\Appointment;
use App\Domains\Organizations\Models\LegalEntity;
use App\Domains\Organizations\Models\Location;
use App\Domains\Patients\Models\Patient;
use App\Domains\Professionals\Models\Professional;
use App\Domains\Professionals\Models\ProfessionalSchedule;
use App\Domains\Users\Enums\RoleCode;
use App\Domains\Users\Enums\UserPermission;
use App\Domains\Users\Models\Role;
use App\Domains\Users\Models\User;
use App\Domains\Users\Services\UserScopeService;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function scopedUser(RoleCode $role = RoleCode::Administrator): User
{
    return User::factory()->create([
        'role_id' => Role::query()->where('code', $role->value)->value('id'),
        'permissions' => UserPermission::ManageAppointments->isAllowedFor($role)
            ? [UserPermission::ManageAppointments->value]
            : [],
    ]);
}

it('unions multiple direct locations with every current and future location of assigned legal entities', function () {
    $user = scopedUser();
    $grantedEntity = LegalEntity::factory()->create();
    $entityLocation = Location::factory()->for($grantedEntity)->create();
    $futureEntityLocation = Location::factory()->for($grantedEntity)->create();
    $directLocation = Location::factory()->create();
    $unassignedLocation = Location::factory()->create();

    $user->legalEntities()->attach($grantedEntity);
    $user->locations()->attach($directLocation);

    $locationIds = app(UserScopeService::class)->locationIdsFor($user);

    expect($locationIds)->toEqualCanonicalizing([
        $entityLocation->getKey(),
        $futureEntityLocation->getKey(),
        $directLocation->getKey(),
    ])->not->toContain($unassignedLocation->getKey());
});

it('limits appointment listings and mutations to assigned locations', function () {
    $user = scopedUser();
    $assignedLocation = Location::factory()->create();
    $otherLocation = Location::factory()->create();
    $user->locations()->attach($assignedLocation);
    $visibleAppointment = Appointment::factory()->for($assignedLocation)->create();
    $hiddenAppointment = Appointment::factory()->for($otherLocation)->create();

    $this->actingAs($user)
        ->get(route('appointments.index', [
            'date' => $visibleAppointment->starts_at->toDateString(),
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('appointments.data', 1)
            ->where('appointments.data.0.id', $visibleAppointment->getKey())
            ->has('locationOptions', 1));

    expect($user->can('update', $visibleAppointment))->toBeTrue()
        ->and($user->can('update', $hiddenAppointment))->toBeFalse();
});

it('derives professional locations from schedules instead of manual grants', function () {
    $professional = Professional::factory()->create();
    $scheduledLocation = Location::factory()->create();
    ProfessionalSchedule::factory()->for($professional)->create([
        'location_id' => $scheduledLocation->getKey(),
    ]);

    expect(app(UserScopeService::class)->locationIdsFor($professional->user))
        ->toBe([$scheduledLocation->getKey()]);
});

it('limits legal entity location counters to the assigned locations', function () {
    $user = scopedUser();
    $legalEntity = LegalEntity::factory()->create();
    $assignedLocation = Location::factory()->for($legalEntity)->create();
    Location::factory()->for($legalEntity)->create();
    $user->locations()->attach($assignedLocation);

    $this->actingAs($user)
        ->get(route('legal_entities.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('legalEntities.data', 1)
            ->where('legalEntities.data.0.id', $legalEntity->getKey())
            ->where('legalEntities.data.0.locations_count', 1));
});

it('keeps patients global while organization and appointment data remain scoped', function () {
    $user = scopedUser();
    $assignedLocation = Location::factory()->create();
    $otherLocation = Location::factory()->create();
    $user->locations()->attach($assignedLocation);
    Appointment::factory()->for($assignedLocation)->create();
    Appointment::factory()->for($otherLocation)->create();
    Patient::factory()->count(2)->create();
    $patientCount = Patient::query()->count();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('appointmentContext.location', 'Sedes asignadas'));

    $this->get(route('patients.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->has('patients.data', $patientCount));
});

it('gives superadmins global scope without organizational assignments', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    expect(app(UserScopeService::class)->locationIdsFor($superAdmin))->toBeNull()
        ->and(app(UserScopeService::class)->legalEntityIdsFor($superAdmin))->toBeNull();
});

it('shares the selectable legal entities and locations with the application shell', function () {
    $user = scopedUser();
    $legalEntity = LegalEntity::factory()->create(['business_name' => 'Clínica Central']);
    $firstLocation = Location::factory()->for($legalEntity)->create(['name' => 'Sede Centro']);
    $secondLocation = Location::factory()->for($legalEntity)->create(['name' => 'Sede Norte']);
    $user->legalEntities()->attach($legalEntity);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('organizationContext.isLocked', false)
            ->has('organizationContext.entities', 1)
            ->where('organizationContext.entities.0.name', 'Clínica Central')
            ->has('organizationContext.entities.0.locations', 2)
            ->where('organizationContext.entities.0.locations.0.id', $firstLocation->getKey())
            ->where('organizationContext.entities.0.locations.1.id', $secondLocation->getKey()));
});

it('persists multiple organizational assignments when inviting a user', function () {
    Mail::fake();
    $superAdmin = User::factory()->superAdmin()->create();
    $role = Role::query()->where('code', RoleCode::Administrator->value)->sole();
    $legalEntities = LegalEntity::factory()->count(2)->create();
    $locations = Location::factory()->count(2)->create();

    $this->actingAs($superAdmin)
        ->post(route('users.store'), [
            'name' => 'Administradora multisede',
            'email' => 'multisede@example.test',
            'role_id' => $role->getKey(),
            'expires_in_hours' => 48,
            'permissions' => [UserPermission::ManageAppointments->value],
            'legal_entity_ids' => $legalEntities->modelKeys(),
            'location_ids' => $locations->modelKeys(),
        ])
        ->assertRedirect(route('users.index'));

    $invitedUser = User::query()->where('email', 'multisede@example.test')->sole();

    expect($invitedUser->legalEntities()->pluck('legal_entities.id')->all())
        ->toEqualCanonicalizing($legalEntities->modelKeys())
        ->and($invitedUser->locations()->pluck('locations.id')->all())
        ->toEqualCanonicalizing($locations->modelKeys());
});
