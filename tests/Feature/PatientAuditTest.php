<?php

use App\Domains\Audits\Models\AuditLog;
use App\Domains\Patients\Enums\PatientStatus;
use App\Domains\Patients\Enums\RegisteredSex;
use App\Domains\Patients\Models\Patient;
use App\Domains\Users\Database\Seeders\RoleSeeder;
use App\Domains\Users\Enums\RoleCode;
use App\Domains\Users\Models\Role;
use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function patientAuditActor(RoleCode $role = RoleCode::SuperAdmin): User
{
    return User::factory()->create([
        'role_id' => Role::query()
            ->where('code', $role->value)
            ->sole()
            ->getKey(),
    ]);
}

/**
 * @return array<string, mixed>
 */
function patientAuditPayload(Patient $patient, array $overrides = []): array
{
    return [
        'lock_version' => $patient->lock_version,
        'first_name' => $patient->first_name,
        'last_name' => $patient->last_name,
        'document_number' => $patient->document_number,
        'birth_date' => $patient->birth_date->toDateString(),
        'registered_sex' => $patient->registered_sex->value,
        'gender' => $patient->gender,
        'phone' => $patient->phone,
        'email' => $patient->email,
        'address' => $patient->address,
        'coverage_name' => 'Particular',
        'member_number' => null,
        'status' => $patient->status->value,
        'contacts' => [],
        ...$overrides,
    ];
}

it('audits patient creation with encrypted administrative snapshots', function () {
    $actor = patientAuditActor();
    $payload = [
        'first_name' => 'Julieta',
        'last_name' => 'Lanteri',
        'document_number' => '40111222',
        'birth_date' => '1985-03-22',
        'registered_sex' => RegisteredSex::Female->value,
        'gender' => 'Mujer',
        'phone' => '1140001122',
        'email' => 'julieta@paciente.test',
        'address' => 'Avenida de Mayo 100, CABA',
        'coverage_name' => 'Particular',
        'member_number' => null,
        'status' => PatientStatus::Active->value,
        'contacts' => [],
    ];

    $this->actingAs($actor)
        ->withHeader('X-Request-ID', 'patient-create-request')
        ->post(route('patients.store'), $payload)
        ->assertRedirect(route('patients.index'));

    $patient = Patient::query()->where('document_number', '40111222')->sole();
    $audit = AuditLog::query()->where('event', 'patient.created')->sole();
    $rawAfterValues = DB::table('audit_logs')
        ->where('id', $audit->getKey())
        ->value('after_values');

    expect($audit->actor_id)
        ->toBe($actor->getKey())
        ->and($audit->auditable_type)->toBe($patient->getMorphClass())
        ->and($audit->auditable_id)->toBe((string) $patient->getKey())
        ->and($audit->correlation_id)->toBe('patient-create-request')
        ->and($audit->after_values['document_number'])->toBe('40111222')
        ->and($rawAfterValues)->toBeString()
        ->and($rawAfterValues)->not->toContain('40111222');
});

it('audits patient list and detail reads', function () {
    $actor = patientAuditActor(RoleCode::Professional);
    $patient = Patient::factory()->create([
        'first_name' => 'Cecilia',
    ]);

    $this->actingAs($actor)
        ->get(route('patients.index', ['search' => 'Cecilia']))
        ->assertSuccessful();
    $this->get(route('patients.show', $patient))->assertSuccessful();

    expect(AuditLog::query()->where('event', 'patient.index.viewed')->count())
        ->toBe(1)
        ->and(AuditLog::query()->where('event', 'patient.viewed')->count())
        ->toBe(1);
});

it('audits patient updates, archive and restore', function () {
    $actor = patientAuditActor();
    $patient = Patient::factory()->create([
        'first_name' => 'Nombre original',
        'birth_date' => '1980-01-01',
        'status' => PatientStatus::Inpatient,
    ]);

    $this->actingAs($actor)
        ->put(
            route('patients.update', $patient),
            patientAuditPayload($patient, [
                'first_name' => 'Nombre rectificado',
            ]),
        )
        ->assertRedirect(route('patients.show', $patient));

    $patient->refresh();

    $this->delete(route('patients.destroy', $patient))
        ->assertRedirect(route('patients.index'));
    $this->patch(route('patients.restore', $patient))
        ->assertRedirect(route('patients.index'));

    $events = AuditLog::query()
        ->orderBy('id')
        ->pluck('event')
        ->all();

    expect($events)
        ->toContain('patient.updated')
        ->toContain('patient.archived')
        ->toContain('patient.restored')
        ->and($patient->refresh()->status)->toBe(PatientStatus::Inpatient);
});

it('does not write a successful update audit when optimistic locking rejects it', function () {
    $actor = patientAuditActor();
    $patient = Patient::factory()->create([
        'birth_date' => '1980-01-01',
    ]);

    $this->actingAs($actor)
        ->put(
            route('patients.update', $patient),
            patientAuditPayload($patient, [
                'lock_version' => $patient->lock_version + 1,
            ]),
        )
        ->assertSessionHasErrors('lock_version');

    expect(AuditLog::query()->where('event', 'patient.updated')->exists())
        ->toBeFalse();
});
