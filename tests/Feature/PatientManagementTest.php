<?php

use App\Domains\Audits\Models\AuditLog;
use App\Domains\Patients\Enums\PatientStatus;
use App\Domains\Patients\Enums\RegisteredSex;
use App\Domains\Patients\Models\Patient;
use App\Domains\Users\Enums\RoleCode;
use App\Domains\Users\Enums\UserStatus;
use App\Domains\Users\Mail\UserInvitationMail;
use App\Domains\Users\Models\Role;
use App\Domains\Users\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function patientsActor(RoleCode $role): User
{
    return User::factory()->create([
        'role_id' => Role::query()->where('code', $role->value)->sole()->getKey(),
    ]);
}

/**
 * @return array<string, mixed>
 */
function validPatientPayload(array $overrides = []): array
{
    return [
        'first_name' => 'Cecilia',
        'last_name' => 'Grierson',
        'document_number' => '12345678',
        'birth_date' => '1985-11-22',
        'registered_sex' => RegisteredSex::Female->value,
        'gender' => 'Mujer',
        'phone' => '1144445555',
        'email' => 'cecilia.grierson@paciente.test',
        'address' => 'Av. de Mayo 1200, CABA',
        'coverage_name' => 'OSDE 310',
        'member_number' => 'OSDE-998877',
        'status' => PatientStatus::Active->value,
        'contacts' => [
            [
                'full_name' => 'María Grierson',
                'relationship' => 'Hermana',
                'phone' => '1166667777',
                'email' => 'maria.grierson@contacto.test',
                'is_emergency_contact' => true,
                'is_legal_representative' => false,
                'is_financially_responsible' => false,
            ],
            [
                'full_name' => 'Juan Grierson',
                'relationship' => 'Cónyuge',
                'phone' => '1155558888',
                'email' => null,
                'is_emergency_contact' => false,
                'is_legal_representative' => false,
                'is_financially_responsible' => true,
            ],
        ],
        ...$overrides,
    ];
}

/**
 * @return array{
 *     viewAny: bool,
 *     view: bool,
 *     create: bool,
 *     update: bool,
 *     delete: bool,
 *     restore: bool,
 *     manageStatus: bool
 * }
 */
function patientAbilities(
    bool $read,
    bool $create,
    bool $update,
    bool $archive,
): array {
    return [
        'viewAny' => $read,
        'view' => $read,
        'create' => $create,
        'update' => $update,
        'delete' => $archive,
        'restore' => $archive,
        'manageStatus' => $archive,
    ];
}

it('redirects guests from every patient management route', function () {
    $patient = Patient::factory()->create();

    $this->get(route('patients.index'))->assertRedirect(route('login'));
    $this->get(route('patients.create'))->assertRedirect(route('login'));
    $this->post(route('patients.store'), [])->assertRedirect(route('login'));
    $this->get(route('patients.show', $patient))->assertRedirect(route('login'));
    $this->get(route('patients.external_clinical_record', $patient))->assertRedirect(route('login'));
    $this->get(route('patients.edit', $patient))->assertRedirect(route('login'));
    $this->put(route('patients.update', $patient), [])->assertRedirect(route('login'));
    $this->delete(route('patients.destroy', $patient))->assertRedirect(route('login'));
    $this->patch(route('patients.restore', $patient))->assertRedirect(route('login'));
});

it('enforces the complete patient authorization matrix', function (
    RoleCode $role,
    array $abilities,
) {
    $actor = patientsActor($role);
    $patient = Patient::factory()->create();
    $archivedPatient = Patient::factory()->create([
        'status' => PatientStatus::Archived,
        'archived_at' => now(),
    ]);

    if ($role === RoleCode::Patient) {
        Patient::factory()->for($actor)->create();
    }

    expect($actor->can('viewAny', Patient::class))->toBe($abilities['viewAny'])
        ->and($actor->can('view', $patient))->toBe($abilities['view'])
        ->and($actor->can('create', Patient::class))->toBe($abilities['create'])
        ->and($actor->can('update', $patient))->toBe($abilities['update'])
        ->and($actor->can('delete', $patient))->toBe($abilities['delete'])
        ->and($actor->can('restore', $archivedPatient))->toBe($abilities['restore'])
        ->and($actor->can('manageStatus', $patient))->toBe($abilities['manageStatus'])
        ->and($actor->can('forceDelete', $patient))->toBeFalse();

    $this->actingAs($actor);

    $indexResponse = $this->get(route('patients.index'));
    $showResponse = $this->get(route('patients.show', $patient));

    if ($abilities['viewAny']) {
        $indexResponse->assertSuccessful();
    } else {
        $indexResponse->assertForbidden();
    }

    if ($abilities['view']) {
        $showResponse->assertSuccessful();
    } else {
        $showResponse->assertForbidden();
    }

    $createResponse = $this->get(route('patients.create'));
    $storeResponse = $this->post(route('patients.store'), []);

    if ($abilities['create']) {
        $createResponse->assertSuccessful();
        $storeResponse->assertSessionHasErrors();
    } else {
        $createResponse->assertForbidden();
        $storeResponse->assertForbidden();
    }

    $editResponse = $this->get(route('patients.edit', $patient));
    $updateResponse = $this->put(route('patients.update', $patient), []);

    if ($abilities['update']) {
        $editResponse->assertSuccessful();
        $updateResponse->assertSessionHasErrors();
    } else {
        $editResponse->assertForbidden();
        $updateResponse->assertForbidden();
    }

    $archiveResponse = $this->delete(route('patients.destroy', $patient));

    if ($abilities['delete']) {
        $archiveResponse
            ->assertRedirect(route('patients.index'))
            ->assertInertiaFlash('success');

        $restoreResponse = $this->patch(route('patients.restore', $patient));

        $restoreResponse
            ->assertRedirect(route('patients.index'))
            ->assertInertiaFlash('success');
    } else {
        $archiveResponse->assertForbidden();
        $this->patch(route('patients.restore', $patient))->assertForbidden();
    }
})->with([
    'superadmin' => [
        RoleCode::SuperAdmin,
        patientAbilities(
            read: true,
            create: true,
            update: true,
            archive: true,
        ),
    ],
    'administrator' => [
        RoleCode::Administrator,
        patientAbilities(
            read: true,
            create: true,
            update: true,
            archive: true,
        ),
    ],
    'manager' => [
        RoleCode::Manager,
        patientAbilities(read: true, create: true, update: true, archive: true),
    ],
    'supervisor' => [
        RoleCode::Supervisor,
        patientAbilities(read: true, create: true, update: true, archive: true),
    ],
    'reception' => [
        RoleCode::Reception,
        patientAbilities(read: true, create: true, update: true, archive: false),
    ],
    'professional' => [
        RoleCode::Professional,
        patientAbilities(
            read: true,
            create: false,
            update: false,
            archive: false,
        ),
    ],
    'nursing' => [
        RoleCode::Nursing,
        patientAbilities(
            read: true,
            create: false,
            update: false,
            archive: false,
        ),
    ],
    'emergency' => [
        RoleCode::Emergency,
        patientAbilities(
            read: true,
            create: false,
            update: false,
            archive: false,
        ),
    ],
    'accounting' => [
        RoleCode::Accounting,
        patientAbilities(read: false, create: false, update: false, archive: false),
    ],
    'sales' => [
        RoleCode::Sales,
        patientAbilities(read: false, create: false, update: false, archive: false),
    ],
    'auditor' => [
        RoleCode::Auditor,
        patientAbilities(read: false, create: false, update: false, archive: false),
    ],
    'patient' => [
        RoleCode::Patient,
        patientAbilities(read: false, create: false, update: false, archive: false),
    ],
]);

it('renders a filtered paginated index with summaries options and permissions', function () {
    $actor = patientsActor(RoleCode::SuperAdmin);
    $matching = Patient::factory()->create([
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'document_number' => '27123456',
        'coverage_name' => 'OSDE 310',
        'status' => PatientStatus::Active,
    ]);
    Patient::factory()->create([
        'first_name' => 'Grace',
        'last_name' => 'Hopper',
        'document_number' => '28234567',
        'status' => PatientStatus::Inpatient,
    ]);
    Patient::factory()->create([
        'first_name' => 'Dorothy',
        'last_name' => 'Vaughan',
        'document_number' => '29345678',
        'status' => PatientStatus::Archived,
        'archived_at' => now(),
    ]);

    $response = $this->actingAs($actor)
        ->get(route('patients.index', [
            'search' => 'Ada',
            'status' => PatientStatus::Active->value,
            'per_page' => 10,
        ]))
        ->assertSuccessful();

    $response
        ->assertDontSee('Alerta clínica que no debe integrar el padrón.', false)
        ->assertInertia(fn (Assert $page) => $page
            ->component('patients/views/Index')
            ->has('patients.data', 1, fn (Assert $patient) => $patient
                ->where('id', $matching->getKey())
                ->where('first_name', 'Ada')
                ->where('last_name', 'Lovelace')
                ->where('document_number', '27123456')
                ->where('status.value', PatientStatus::Active->value)
                ->missing('registered_sex')
                ->missing('gender')
                ->missing('email')
                ->missing('address')
                ->missing('clinical_alerts')
                ->missing('has_clinical_alerts')
                ->etc())
            ->where('filters.search', 'Ada')
            ->where('filters.status', PatientStatus::Active->value)
            ->where('patients.per_page', 10)
            ->has('summaries', fn (Assert $summaries) => $summaries
                ->where('total', 3)
                ->where('active', 1)
                ->where('inpatient', 1)
                ->where('archived', 1)
                ->etc())
            ->has('statusOptions', count(PatientStatus::cases()))
            ->has('registeredSexOptions', count(RegisteredSex::cases()))
            ->has('permissions'));
});

it('creates a patient and all their contacts', function () {
    Mail::fake();
    $actor = patientsActor(RoleCode::SuperAdmin);

    $this->actingAs($actor)
        ->post(route('patients.store'), validPatientPayload())
        ->assertRedirect(route('patients.index'))
        ->assertInertiaFlash('success');

    $patient = Patient::query()
        ->where('document_number', '12345678')
        ->sole();

    expect($patient->first_name)
        ->toBe('Cecilia')
        ->and($patient->last_name)->toBe('Grierson')
        ->and($patient->registered_sex)->toBe(RegisteredSex::Female)
        ->and($patient->status)->toBe(PatientStatus::Active)
        ->and($patient->contacts()->count())->toBe(2)
        ->and($patient->user)->not->toBeNull()
        ->and($patient->user->role?->code)->toBe(RoleCode::Patient)
        ->and($patient->user->status)->toBe(UserStatus::PendingInvitation);

    Mail::assertQueued(
        UserInvitationMail::class,
        fn (UserInvitationMail $mail): bool => $mail->hasTo($patient->email),
    );

    $this->assertDatabaseHas('patient_contacts', [
        'patient_id' => $patient->getKey(),
        'full_name' => 'María Grierson',
        'is_emergency_contact' => true,
    ]);
    $this->assertDatabaseHas('patient_contacts', [
        'patient_id' => $patient->getKey(),
        'full_name' => 'Juan Grierson',
        'is_financially_responsible' => true,
    ]);
});

it('limits reception to an active administrative record when creating patients', function () {
    $reception = patientsActor(RoleCode::Reception);

    $this->actingAs($reception)
        ->post(route('patients.store'), validPatientPayload([
            'status' => PatientStatus::Inpatient->value,
        ]))
        ->assertSessionHasErrors('status');

    $administrativePayload = validPatientPayload([
        'document_number' => '34567890',
        'email' => 'alta.recepcion@paciente.test',
        'status' => PatientStatus::Active->value,
    ]);
    $this->post(route('patients.store'), $administrativePayload)
        ->assertRedirect(route('patients.index'));

    $patient = Patient::query()
        ->where('document_number', '34567890')
        ->sole();

    expect($patient->status)->toBe(PatientStatus::Active);
});

it('requires a unique DNI', function () {
    $actor = patientsActor(RoleCode::SuperAdmin);
    Patient::factory()->create([
        'document_number' => '12345678',
    ]);

    $this->actingAs($actor)
        ->post(route('patients.store'), validPatientPayload([
            'email' => 'dni.duplicado@paciente.test',
        ]))
        ->assertSessionHasErrors('document_number');

    expect(Patient::query()->where('document_number', '12345678')->count())->toBe(1);
});

it('allows Particular without a member number and requires it for other coverage', function () {
    $actor = patientsActor(RoleCode::SuperAdmin);

    $this->actingAs($actor)
        ->post(route('patients.store'), validPatientPayload([
            'document_number' => '30345678',
            'email' => 'particular@paciente.test',
            'coverage_name' => 'pArTiCuLaR',
            'member_number' => 'NO-DEBE-PERSISTIR',
        ]))
        ->assertRedirect(route('patients.index'));

    $this->assertDatabaseHas('patients', [
        'document_number' => '30345678',
        'member_number' => null,
    ]);

    $this->post(route('patients.store'), validPatientPayload([
        'document_number' => '31456789',
        'email' => 'sin.afiliado@paciente.test',
        'coverage_name' => 'OSDE 210',
        'member_number' => null,
    ]))->assertSessionHasErrors('member_number');

    $this->assertDatabaseMissing('patients', [
        'document_number' => '31456789',
    ]);
});

it('requires a legal representative when the patient is a minor', function () {
    $actor = patientsActor(RoleCode::SuperAdmin);
    $minorBirthDate = now()->subYears(16)->format('Y-m-d');

    $this->actingAs($actor)
        ->post(route('patients.store'), validPatientPayload([
            'document_number' => '32567890',
            'email' => 'menor.sin.representante@paciente.test',
            'birth_date' => $minorBirthDate,
            'contacts' => [
                [
                    'full_name' => 'Contacto de emergencia',
                    'relationship' => 'Tía',
                    'phone' => '1145678901',
                    'email' => null,
                    'is_emergency_contact' => true,
                    'is_legal_representative' => false,
                    'is_financially_responsible' => false,
                ],
            ],
        ]))
        ->assertSessionHasErrors('contacts');

    $this->post(route('patients.store'), validPatientPayload([
        'document_number' => '33678901',
        'email' => 'menor.con.representante@paciente.test',
        'birth_date' => $minorBirthDate,
        'contacts' => [
            [
                'full_name' => 'Representante legal',
                'relationship' => 'Madre',
                'phone' => '1156789012',
                'email' => 'representante@contacto.test',
                'is_emergency_contact' => true,
                'is_legal_representative' => true,
                'is_financially_responsible' => true,
            ],
        ],
    ]))->assertRedirect(route('patients.index'));

    $patient = Patient::query()
        ->where('document_number', '33678901')
        ->sole();

    expect($patient->contacts()->where('is_legal_representative', true)->exists())
        ->toBeTrue();
});

it('replaces every patient contact during an update', function () {
    $actor = patientsActor(RoleCode::SuperAdmin);

    $this->actingAs($actor)
        ->post(route('patients.store'), validPatientPayload())
        ->assertRedirect(route('patients.index'));

    $patient = Patient::query()
        ->where('document_number', '12345678')
        ->sole();
    $oldContactIds = $patient->contacts()->pluck('id');

    $this->put(route('patients.update', $patient), validPatientPayload([
        'lock_version' => $patient->lock_version,
        'first_name' => '  Cecilia actualizada  ',
        'email' => 'ACTUALIZADA@PACIENTE.TEST',
        'contacts' => [
            [
                'full_name' => 'Nuevo contacto',
                'relationship' => 'Hijo',
                'phone' => '1198765432',
                'email' => 'nuevo.contacto@paciente.test',
                'is_emergency_contact' => true,
                'is_legal_representative' => false,
                'is_financially_responsible' => true,
            ],
        ],
    ]))
        ->assertRedirect(route('patients.show', $patient))
        ->assertInertiaFlash('success');

    $patient->refresh();

    expect($patient->first_name)
        ->toBe('Cecilia actualizada')
        ->and($patient->email)->toBe('actualizada@paciente.test')
        ->and($patient->contacts()->count())->toBe(1)
        ->and($patient->contacts()->sole()->full_name)->toBe('Nuevo contacto')
        ->and($patient->contacts()->whereIn('id', $oldContactIds)->exists())->toBeFalse();
});

it('lets reception edit administrative data without changing status', function () {
    $reception = patientsActor(RoleCode::Reception);
    $administrator = patientsActor(RoleCode::Administrator);
    $patient = Patient::factory()->create([
        'status' => PatientStatus::Active,
    ]);
    $administrativePayload = validPatientPayload([
        'lock_version' => $patient->lock_version,
        'document_number' => $patient->document_number,
        'first_name' => 'Nombre administrativo',
    ]);
    unset($administrativePayload['status']);

    $this->actingAs($reception)
        ->put(route('patients.update', $patient), $administrativePayload)
        ->assertRedirect(route('patients.show', $patient))
        ->assertInertiaFlash('success');

    $patient->refresh();

    expect($patient->first_name)
        ->toBe('Nombre administrativo')
        ->and($patient->status)->toBe(PatientStatus::Active);

    $this->put(route('patients.update', $patient), [
        ...$administrativePayload,
        'status' => PatientStatus::Inpatient->value,
    ])->assertSessionHasErrors('status');

    $patient->refresh();

    expect($patient->status)->toBe(PatientStatus::Active);

    $this->actingAs($administrator)
        ->put(route('patients.update', $patient), [
            ...$administrativePayload,
            'lock_version' => $patient->lock_version,
            'status' => PatientStatus::Inpatient->value,
        ])
        ->assertRedirect(route('patients.show', $patient));

    $patient->refresh();

    expect($patient->status)->toBe(PatientStatus::Inpatient);
});

it('starts and clears the two-month portal grace period when patient status changes', function () {
    $actor = patientsActor(RoleCode::Administrator);
    $patientUser = patientsActor(RoleCode::Patient);
    $patient = Patient::factory()->for($patientUser)->create();

    $this->actingAs($actor)
        ->put(route('patients.update', $patient), validPatientPayload([
            'document_number' => $patient->document_number,
            'email' => $patientUser->email,
            'lock_version' => $patient->lock_version,
            'status' => PatientStatus::Blocked->value,
        ]))
        ->assertRedirect(route('patients.show', $patient));

    $patient->refresh();
    expect(abs($patient->portal_access_until?->diffInSeconds(now()->addMonthsNoOverflow(2)) ?? 999))->toBeLessThanOrEqual(1)
        ->and($patientUser->fresh()->status)->toBe(UserStatus::Active);

    $this->put(route('patients.update', $patient), validPatientPayload([
        'document_number' => $patient->document_number,
        'email' => $patientUser->email,
        'lock_version' => $patient->lock_version,
        'status' => PatientStatus::Active->value,
    ]))->assertRedirect(route('patients.show', $patient));

    expect($patient->refresh()->portal_access_until)->toBeNull();
});

it('blocks editing an archived patient until it is restored', function () {
    $actor = patientsActor(RoleCode::SuperAdmin);
    $patient = Patient::factory()->create([
        'status' => PatientStatus::Archived,
        'archived_at' => now(),
    ]);

    $this->actingAs($actor)
        ->get(route('patients.edit', $patient))
        ->assertForbidden();

    $this->put(
        route('patients.update', $patient),
        validPatientPayload(['document_number' => $patient->document_number]),
    )->assertForbidden();

    $this->patch(route('patients.restore', $patient))
        ->assertRedirect(route('patients.index'));

    $patient->refresh();

    $this->get(route('patients.edit', $patient))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('patients/views/Edit')
            ->where('patient.id', $patient->getKey()));

    $this->put(
        route('patients.update', $patient),
        validPatientPayload([
            'lock_version' => $patient->lock_version,
            'document_number' => $patient->document_number,
            'first_name' => 'Paciente restaurado',
        ]),
    )->assertRedirect(route('patients.show', $patient));

    expect($patient->refresh()->first_name)->toBe('Paciente restaurado');
});

it('archives and restores a patient without deleting their information', function () {
    $actor = patientsActor(RoleCode::SuperAdmin);
    $patientUser = patientsActor(RoleCode::Patient);
    $patient = Patient::factory()->for($patientUser)->create();
    $contact = $patient->contacts()->create([
        'full_name' => 'Contacto persistente',
        'relationship' => 'Hermano',
        'phone' => '1144455566',
        'email' => null,
        'is_emergency_contact' => true,
        'is_legal_representative' => false,
        'is_financially_responsible' => false,
    ]);

    $this->actingAs($actor)
        ->delete(route('patients.destroy', $patient))
        ->assertRedirect(route('patients.index'))
        ->assertInertiaFlash('success');

    $patient->refresh();

    expect($patient->status)
        ->toBe(PatientStatus::Archived)
        ->and($patient->archived_at)->not->toBeNull()
        ->and(abs($patient->portal_access_until?->diffInSeconds(now()->addMonthsNoOverflow(2)) ?? 999))->toBeLessThanOrEqual(1)
        ->and($patientUser->fresh()->status)->toBe(UserStatus::Active)
        ->and(Patient::query()->whereKey($patient->getKey())->exists())->toBeTrue()
        ->and($patient->contacts()->whereKey($contact->getKey())->exists())->toBeTrue()
        ->and(AuditLog::query()->where('event', 'patient.archived')->sole()->before_values)->toBe([])
        ->and(AuditLog::query()->where('event', 'patient.archived')->sole()->after_values)->toBe([]);

    $this->patch(route('patients.restore', $patient))
        ->assertRedirect(route('patients.index'))
        ->assertInertiaFlash('success');

    $patient->refresh();

    expect($patient->status)
        ->toBe(PatientStatus::Active)
        ->and($patient->archived_at)->toBeNull()
        ->and($patient->portal_access_until)->toBeNull()
        ->and($patient->contacts()->whereKey($contact->getKey())->exists())->toBeTrue();
});

it('registers no permanent-deletion route and never authorizes force delete', function () {
    $expectedPatientRoutes = [
        'patients.create',
        'patients.destroy',
        'patients.edit',
        'patients.external_clinical_record',
        'patients.index',
        'patients.restore',
        'patients.show',
        'patients.store',
        'patients.update',
    ];
    $registeredPatientRoutes = collect(Route::getRoutes())
        ->map(fn ($route): ?string => $route->getName())
        ->filter(fn (?string $name): bool => str_starts_with((string) $name, 'patients.'))
        ->sort()
        ->values()
        ->all();
    $patient = Patient::factory()->create();

    expect($registeredPatientRoutes)->toBe($expectedPatientRoutes);

    foreach ([
        RoleCode::SuperAdmin,
        RoleCode::Administrator,
        RoleCode::Manager,
        RoleCode::Supervisor,
    ] as $role) {
        expect(patientsActor($role)->can('forceDelete', $patient))->toBeFalse();
    }

    expect(fn () => $patient->delete())->toThrow(LogicException::class);

    $this->assertModelExists($patient);
});
