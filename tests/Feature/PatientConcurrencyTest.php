<?php

use App\Domains\Patients\Enums\PatientStatus;
use App\Domains\Patients\Enums\RegisteredSex;
use App\Domains\Patients\Models\Patient;
use App\Domains\Users\Enums\RoleCode;
use App\Domains\Users\Models\Role;
use App\Domains\Users\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function patientConcurrencyActorForTests(): User
{
    return User::factory()->create([
        'role_id' => Role::query()
            ->where('code', RoleCode::SuperAdmin->value)
            ->sole()
            ->getKey(),
    ]);
}

/**
 * @return array<string, mixed>
 */
function patientConcurrencyPayloadForTests(array $overrides = []): array
{
    static $documentNumber = 82000000;

    $documentNumber++;

    return [
        'lock_version' => 1,
        'first_name' => 'René',
        'last_name' => 'Favaloro',
        'document_number' => (string) $documentNumber,
        'birth_date' => '1970-07-12',
        'registered_sex' => RegisteredSex::Male->value,
        'gender' => 'Varón',
        'phone' => '1144445555',
        'email' => 'rene.favaloro@paciente.test',
        'address' => 'Av. Belgrano 1700, CABA',
        'coverage_name' => 'Particular',
        'member_number' => null,
        'status' => PatientStatus::Active->value,
        'contacts' => [
            [
                'full_name' => 'Contacto Favaloro',
                'relationship' => 'Familiar',
                'phone' => '1166667777',
                'email' => 'contacto.favaloro@paciente.test',
                'is_emergency_contact' => true,
                'is_legal_representative' => false,
                'is_financially_responsible' => false,
            ],
        ],
        ...$overrides,
    ];
}

it('requires the version used to edit a patient', function () {
    $actor = patientConcurrencyActorForTests();
    $patient = Patient::factory()->create();
    $payload = patientConcurrencyPayloadForTests([
        'document_number' => $patient->document_number,
    ]);
    unset($payload['lock_version']);

    $this->actingAs($actor)
        ->put(route('patients.update', $patient), $payload)
        ->assertSessionHasErrors('lock_version');

    expect($patient->refresh()->lock_version)->toBe(1);
});

it('increments the version and rejects an update based on stale data', function () {
    $actor = patientConcurrencyActorForTests();
    $patient = Patient::factory()->create();
    $originalVersion = $patient->lock_version;

    $this->actingAs($actor)
        ->put(route('patients.update', $patient), patientConcurrencyPayloadForTests([
            'lock_version' => $originalVersion,
            'document_number' => $patient->document_number,
            'first_name' => 'Primera edición',
        ]))
        ->assertRedirect(route('patients.show', $patient));

    $patient->refresh();

    expect($patient->first_name)
        ->toBe('Primera edición')
        ->and($patient->lock_version)->toBe($originalVersion + 1);

    $this->put(route('patients.update', $patient), patientConcurrencyPayloadForTests([
        'lock_version' => $originalVersion,
        'document_number' => $patient->document_number,
        'first_name' => 'Edición obsoleta',
    ]))->assertSessionHasErrors('lock_version');

    expect($patient->refresh()->first_name)
        ->toBe('Primera edición')
        ->and($patient->lock_version)->toBe($originalVersion + 1);
});

it('restores the valid status that existed before archiving', function (
    PatientStatus $originalStatus,
) {
    $actor = patientConcurrencyActorForTests();
    $patient = Patient::factory()->create([
        'status' => $originalStatus,
    ]);

    $this->actingAs($actor)
        ->delete(route('patients.destroy', $patient))
        ->assertRedirect(route('patients.index'));

    $patient->refresh();

    expect($patient->status)
        ->toBe(PatientStatus::Archived)
        ->and($patient->previous_status)->toBe($originalStatus)
        ->and($patient->lock_version)->toBe(2)
        ->and($patient->archived_at)->not->toBeNull();

    $this->patch(route('patients.restore', $patient))
        ->assertRedirect(route('patients.index'));

    $patient->refresh();

    expect($patient->status)
        ->toBe($originalStatus)
        ->and($patient->previous_status)->toBeNull()
        ->and($patient->lock_version)->toBe(3)
        ->and($patient->archived_at)->toBeNull();
})->with([
    'active' => PatientStatus::Active,
    'inpatient' => PatientStatus::Inpatient,
    'deceased' => PatientStatus::Deceased,
    'blocked' => PatientStatus::Blocked,
]);

it('normalizes nested contact emails before store and update validation', function () {
    $actor = patientConcurrencyActorForTests();
    $createPayload = patientConcurrencyPayloadForTests([
        'contacts' => [
            [
                'full_name' => 'Contacto Inicial',
                'relationship' => 'Familiar',
                'phone' => null,
                'email' => '  CONTACTO.INICIAL@PACIENTE.TEST  ',
                'is_emergency_contact' => true,
                'is_legal_representative' => false,
                'is_financially_responsible' => false,
            ],
        ],
    ]);

    $this->actingAs($actor)
        ->post(route('patients.store'), $createPayload)
        ->assertRedirect(route('patients.index'));

    $patient = Patient::query()
        ->where('document_number', $createPayload['document_number'])
        ->sole();

    expect($patient->contacts()->sole()->email)
        ->toBe('contacto.inicial@paciente.test');

    $this->put(route('patients.update', $patient), patientConcurrencyPayloadForTests([
        'lock_version' => $patient->lock_version,
        'document_number' => $patient->document_number,
        'contacts' => [
            [
                'full_name' => 'Contacto Actualizado',
                'relationship' => 'Familiar',
                'phone' => null,
                'email' => 'CONTACTO.ACTUALIZADO@PACIENTE.TEST',
                'is_emergency_contact' => true,
                'is_legal_representative' => false,
                'is_financially_responsible' => false,
            ],
        ],
    ]))->assertRedirect(route('patients.show', $patient));

    expect($patient->refresh()->contacts()->sole()->email)
        ->toBe('contacto.actualizado@paciente.test');
});
