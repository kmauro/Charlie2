<?php

use App\Domains\Audits\Models\AuditLog;
use App\Domains\Organizations\Enums\LocationService;
use App\Domains\Organizations\Enums\OrganizationStatus;
use App\Domains\Organizations\Models\LegalEntity;
use App\Domains\Organizations\Models\Location;
use App\Domains\Patients\Models\Patient;
use App\Domains\Users\Database\Seeders\RoleSeeder;
use App\Domains\Users\Enums\RoleCode;
use App\Domains\Users\Models\Role;
use App\Domains\Users\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function organizationUser(RoleCode $roleCode): User
{
    $role = Role::query()->where('code', $roleCode->value)->sole();

    $user = User::factory()->create(['role_id' => $role->getKey()]);

    if ($roleCode === RoleCode::Patient) {
        Patient::factory()->for($user)->create();
    }

    return $user;
}

/** @return array<string, mixed> */
function validLegalEntityPayload(array $overrides = []): array
{
    return [
        'business_name' => ' Clínica Federal S.A. ',
        'cuit' => '30-70721665-0',
        'fiscal_address' => ' Av. Rivadavia 1234, CABA ',
        'status' => OrganizationStatus::Active->value,
        ...$overrides,
    ];
}

/** @return array<string, mixed> */
function validLocationPayload(LegalEntity $legalEntity, array $overrides = []): array
{
    return [
        'legal_entity_id' => $legalEntity->getKey(),
        'name' => ' Sede Centro ',
        'code' => ' cen-01 ',
        'address' => ' Av. Corrientes 1550 ',
        'neighborhood' => ' San Nicolás ',
        'postal_code' => 'C1042',
        'phone' => ' 1140001234 ',
        'email' => 'SEDE.CENTRO@CLINICA.TEST',
        'opening_date' => '2026-08-01',
        'opening_time' => '08:00',
        'closing_time' => '20:00',
        'timezone' => 'America/Argentina/Buenos_Aires',
        'services' => [
            LocationService::Outpatient->value,
            LocationService::CashDesk->value,
        ],
        'status' => OrganizationStatus::Active->value,
        ...$overrides,
    ];
}

it('requires authentication for organization screens', function () {
    $this->get(route('organizations.index'))->assertRedirect(route('login'));
    $this->get(route('legal_entities.index'))->assertRedirect(route('login'));
});

it('allows management roles to view locations and legal entities', function (RoleCode $roleCode) {
    $actor = organizationUser($roleCode);

    $this->actingAs($actor)
        ->get(route('organizations.index'))
        ->assertSuccessful();
    $this->get(route('legal_entities.index'))->assertSuccessful();
})->with([
    'superadmin' => RoleCode::SuperAdmin,
    'administrator' => RoleCode::Administrator,
    'manager' => RoleCode::Manager,
    'supervisor' => RoleCode::Supervisor,
]);

it('denies organization access to roles outside management', function (RoleCode $roleCode) {
    $actor = organizationUser($roleCode);

    $this->actingAs($actor)
        ->get(route('organizations.index'))
        ->assertForbidden();
    $this->get(route('legal_entities.index'))->assertForbidden();
})->with([
    'reception' => RoleCode::Reception,
    'professional' => RoleCode::Professional,
    'accounting' => RoleCode::Accounting,
    'patient' => RoleCode::Patient,
]);

it('allows only superadmins and administrators to mutate organizations', function (RoleCode $roleCode) {
    $actor = organizationUser($roleCode);
    $legalEntity = LegalEntity::factory()->create();
    $location = Location::factory()->for($legalEntity)->create();

    if ($roleCode !== RoleCode::SuperAdmin) {
        $actor->legalEntities()->attach($legalEntity);
        $actor->locations()->attach($location);
    }

    expect($actor->can('create', Location::class))
        ->toBeTrue()
        ->and($actor->can('update', $location))->toBeTrue()
        ->and($actor->can('create', LegalEntity::class))
        ->toBe($roleCode === RoleCode::SuperAdmin)
        ->and($actor->can('update', $legalEntity))->toBeTrue();
})->with([
    'superadmin' => RoleCode::SuperAdmin,
    'administrator' => RoleCode::Administrator,
]);

it('keeps managers and supervisors in read-only mode', function (RoleCode $roleCode) {
    $actor = organizationUser($roleCode);
    $legalEntity = LegalEntity::factory()->create();
    $location = Location::factory()->for($legalEntity)->create();

    $this->actingAs($actor)
        ->get(route('organizations.create'))
        ->assertForbidden();
    $this->post(route('organizations.store'), validLocationPayload($legalEntity))
        ->assertForbidden();
    $this->get(route('organizations.edit', $location))->assertForbidden();
    $this->delete(route('organizations.destroy', $location))->assertForbidden();
    $this->get(route('legal_entities.create'))->assertForbidden();
    $this->post(route('legal_entities.store'), validLegalEntityPayload())
        ->assertForbidden();
})->with([
    'manager' => RoleCode::Manager,
    'supervisor' => RoleCode::Supervisor,
]);

it('renders persisted organization data without frontend mocks', function () {
    $actor = organizationUser(RoleCode::SuperAdmin);
    $legalEntity = LegalEntity::factory()->create([
        'business_name' => 'Entidad Persistida S.A.',
        'cuit' => '30707216650',
    ]);
    $location = Location::factory()->for($legalEntity)->create([
        'name' => 'Sede Persistida',
        'code' => 'PER',
        'services' => [LocationService::Emergency->value],
    ]);

    $this->actingAs($actor)
        ->get(route('organizations.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('organizations/views/Index')
            ->has('locations.data', 1, fn (Assert $item) => $item
                ->where('id', $location->getKey())
                ->where('name', 'Sede Persistida')
                ->where('legal_entity.business_name', 'Entidad Persistida S.A.')
                ->where('service_labels.0', 'Guardia')
                ->has('permissions')
                ->etc())
            ->where('summaries.locations_total', 1)
            ->where('summaries.legal_entities_total', 1)
            ->has('legalEntities', 1)
            ->has('statusOptions', count(OrganizationStatus::cases())));

    $this->get(route('legal_entities.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('organizations/legal-entities/Index')
            ->has('legalEntities.data', 1, fn (Assert $item) => $item
                ->where('id', $legalEntity->getKey())
                ->where('business_name', 'Entidad Persistida S.A.')
                ->where('formatted_cuit', '30-70721665-0')
                ->where('locations_count', 1)
                ->etc()));
});

it('creates legal entities with canonical CUIT and an audit event', function () {
    $actor = organizationUser(RoleCode::SuperAdmin);

    $this->actingAs($actor)
        ->post(route('legal_entities.store'), validLegalEntityPayload())
        ->assertRedirect(route('legal_entities.index'))
        ->assertInertiaFlash('success');

    $legalEntity = LegalEntity::query()->sole();

    expect($legalEntity->business_name)
        ->toBe('Clínica Federal S.A.')
        ->and($legalEntity->cuit)->toBe('30707216650')
        ->and($legalEntity->fiscal_address)->toBe('Av. Rivadavia 1234, CABA')
        ->and($legalEntity->status)->toBe(OrganizationStatus::Active)
        ->and(AuditLog::query()->where('event', 'legal_entity.created')->exists())
        ->toBeTrue();
});

it('rejects malformed and duplicate CUIT values', function () {
    $actor = organizationUser(RoleCode::SuperAdmin);
    LegalEntity::factory()->create(['cuit' => '30707216650']);

    $this->actingAs($actor)
        ->post(route('legal_entities.store'), validLegalEntityPayload([
            'cuit' => '30-70721665',
        ]))
        ->assertSessionHasErrors('cuit');
    $this->post(route('legal_entities.store'), validLegalEntityPayload())
        ->assertSessionHasErrors('cuit');

    expect(LegalEntity::query()->count())->toBe(1);
});

it('creates locations with canonical code normalized contact data and services', function () {
    $actor = organizationUser(RoleCode::SuperAdmin);
    $legalEntity = LegalEntity::factory()->create();

    $this->actingAs($actor)
        ->post(route('organizations.store'), validLocationPayload($legalEntity))
        ->assertRedirect(route('organizations.index'))
        ->assertInertiaFlash('success');

    $location = Location::query()->sole();

    expect($location->legal_entity_id)
        ->toBe($legalEntity->getKey())
        ->and($location->name)->toBe('Sede Centro')
        ->and($location->code)->toBe('CEN-01')
        ->and($location->email)->toBe('sede.centro@clinica.test')
        ->and($location->services)->toBe([
            LocationService::Outpatient->value,
            LocationService::CashDesk->value,
        ])
        ->and(AuditLog::query()->where('event', 'location.created')->exists())
        ->toBeTrue();
});

it('validates location hours services and active legal entity membership', function () {
    $actor = organizationUser(RoleCode::SuperAdmin);
    $archivedLegalEntity = LegalEntity::factory()->archived()->create();

    $this->actingAs($actor)
        ->post(route('organizations.store'), validLocationPayload(
            $archivedLegalEntity,
            [
                'opening_time' => '20:00',
                'closing_time' => '08:00',
                'services' => [],
            ],
        ))
        ->assertSessionHasErrors([
            'legal_entity_id',
            'closing_time',
            'services',
        ]);

    expect(Location::query()->count())->toBe(0);
});

it('updates a location and preserves its database identity', function () {
    $actor = organizationUser(RoleCode::Administrator);
    $legalEntity = LegalEntity::factory()->create();
    $location = Location::factory()->for($legalEntity)->create([
        'code' => 'OLD',
    ]);
    $actor->legalEntities()->attach($legalEntity);
    $actor->locations()->attach($location);

    $this->actingAs($actor)
        ->put(
            route('organizations.update', $location),
            validLocationPayload($legalEntity, [
                'name' => 'Sede Renovada',
                'code' => 'new',
                'status' => OrganizationStatus::Preparation->value,
                'services' => [LocationService::Laboratory->value],
            ]),
        )
        ->assertRedirect(route('organizations.index'));

    $location->refresh();

    expect($location->name)
        ->toBe('Sede Renovada')
        ->and($location->code)->toBe('NEW')
        ->and($location->status)->toBe(OrganizationStatus::Preparation)
        ->and($location->services)->toBe([LocationService::Laboratory->value])
        ->and(AuditLog::query()->where('event', 'location.updated')->exists())
        ->toBeTrue();
});

it('archives and restores locations without physical deletion', function () {
    $actor = organizationUser(RoleCode::SuperAdmin);
    $legalEntity = LegalEntity::factory()->create();
    $location = Location::factory()->for($legalEntity)->create();

    $this->actingAs($actor)
        ->delete(route('organizations.destroy', $location))
        ->assertRedirect(route('organizations.index'));

    $location->refresh();
    expect($location->status)
        ->toBe(OrganizationStatus::Archived)
        ->and($location->archived_at)->not->toBeNull()
        ->and(AuditLog::query()->where('event', 'location.archived')->sole()->before_values)->toBe([])
        ->and(AuditLog::query()->where('event', 'location.archived')->sole()->after_values)->toBe([]);
    $this->assertModelExists($location);

    $this->patch(route('organizations.restore', $location))
        ->assertRedirect(route('organizations.index'));

    $location->refresh();
    expect($location->status)
        ->toBe(OrganizationStatus::Active)
        ->and($location->archived_at)->toBeNull();
});

it('requires every location to be archived before its legal entity', function () {
    $actor = organizationUser(RoleCode::SuperAdmin);
    $legalEntity = LegalEntity::factory()->create();
    $location = Location::factory()->for($legalEntity)->create();

    $this->actingAs($actor)
        ->delete(route('legal_entities.destroy', $legalEntity))
        ->assertSessionHasErrors('status');

    expect($legalEntity->refresh()->status)->toBe(OrganizationStatus::Active);

    $this->delete(route('organizations.destroy', $location));
    $this->delete(route('legal_entities.destroy', $legalEntity))
        ->assertRedirect(route('legal_entities.index'));

    expect($legalEntity->refresh()->status)->toBe(OrganizationStatus::Archived);

    $this->patch(route('legal_entities.restore', $legalEntity))
        ->assertRedirect(route('legal_entities.index'));

    expect($legalEntity->refresh()->status)->toBe(OrganizationStatus::Active);
});

it('requires active locations to be inactivated before inactivating their legal entity', function () {
    $actor = organizationUser(RoleCode::SuperAdmin);
    $legalEntity = LegalEntity::factory()->create([
        'business_name' => 'Entidad Operativa S.A.',
        'cuit' => '30707216650',
    ]);
    $activeLocation = Location::factory()->for($legalEntity)->create([
        'status' => OrganizationStatus::Active,
    ]);
    Location::factory()->for($legalEntity)->create([
        'status' => OrganizationStatus::Preparation,
    ]);

    $this->actingAs($actor)
        ->put(route('legal_entities.update', $legalEntity), validLegalEntityPayload([
            'business_name' => $legalEntity->business_name,
            'cuit' => $legalEntity->cuit,
            'fiscal_address' => $legalEntity->fiscal_address,
            'status' => OrganizationStatus::Inactive->value,
        ]))
        ->assertSessionHasErrors('status');

    expect($legalEntity->refresh()->status)->toBe(OrganizationStatus::Active);

    $activeLocation->update(['status' => OrganizationStatus::Inactive]);

    $this->put(route('legal_entities.update', $legalEntity), validLegalEntityPayload([
        'business_name' => $legalEntity->business_name,
        'cuit' => $legalEntity->cuit,
        'fiscal_address' => $legalEntity->fiscal_address,
        'status' => OrganizationStatus::Inactive->value,
    ]))->assertRedirect(route('legal_entities.index'));

    expect($legalEntity->refresh()->status)->toBe(OrganizationStatus::Inactive);
});

it('never authorizes or permits physical deletion of organization records', function () {
    $actor = organizationUser(RoleCode::SuperAdmin);
    $legalEntity = LegalEntity::factory()->create();
    $location = Location::factory()->for($legalEntity)->create();

    expect($actor->can('forceDelete', $legalEntity))
        ->toBeFalse()
        ->and($actor->can('forceDelete', $location))->toBeFalse()
        ->and(fn () => $location->delete())
        ->toThrow(LogicException::class)
        ->and(fn () => $legalEntity->delete())
        ->toThrow(LogicException::class);
});
