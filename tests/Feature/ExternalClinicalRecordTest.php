<?php

use App\Domains\Appointments\Enums\AppointmentStatus;
use App\Domains\Appointments\Models\Appointment;
use App\Domains\Audits\Models\AuditLog;
use App\Domains\Patients\Models\Patient;
use App\Domains\Professionals\Models\Professional;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    config()->set(
        'services.external_clinical_record.url',
        'https://historia-clinica.example.test',
    );
});

function externalClinicalRecordProfessional(Patient $patient): Professional
{
    $professional = Professional::factory()->create();

    Appointment::factory()
        ->for($patient)
        ->for($professional)
        ->create([
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addMinutes(90),
            'status' => AppointmentStatus::Confirmed,
        ]);

    return $professional->load('user');
}

it('shows only the external access section to a related professional', function () {
    $patient = Patient::factory()->create();
    $relatedProfessional = externalClinicalRecordProfessional($patient);
    $unrelatedProfessional = Professional::factory()->create();

    $this->actingAs($relatedProfessional->user)
        ->get(route('patients.show', $patient))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('patients/views/Show')
            ->where(
                'externalClinicalRecordUrl',
                route('patients.external_clinical_record', $patient),
            )
            ->missing('clinicalAlertsUrl')
            ->missing('clinicalHistoryUrl')
            ->missing('clinicalRecordStoreUrl'));

    $this->actingAs($unrelatedProfessional->user)
        ->get(route('patients.show', $patient))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->missing('externalClinicalRecordUrl'));
});

it('redirects authorized access to the configured external webapp and audits it', function () {
    $patient = Patient::factory()->create();
    $professional = externalClinicalRecordProfessional($patient);
    $externalUrl = 'https://historia-clinica.example.test';

    $this->actingAs($professional->user)
        ->withHeader('X-Request-ID', 'external-record-open')
        ->get(route('patients.external_clinical_record', $patient))
        ->assertRedirect($externalUrl);

    $audit = AuditLog::query()
        ->where('event', 'external_clinical_record.opened')
        ->sole();

    expect($audit->actor_id)
        ->toBe($professional->user->getKey())
        ->and($audit->auditable_id)->toBe((string) $patient->getKey())
        ->and($audit->correlation_id)->toBe('external-record-open')
        ->and($audit->after_values)->toBe([
            'destination_host' => 'historia-clinica.example.test',
        ])
        ->and($externalUrl)->not->toContain($patient->document_number)
        ->and($externalUrl)->not->toContain((string) $patient->getKey());
});

it('uses an inertia location response for external navigation', function () {
    $patient = Patient::factory()->create();
    $professional = externalClinicalRecordProfessional($patient);

    $this->actingAs($professional->user)
        ->withHeader('X-Inertia', 'true')
        ->withHeader(
            'X-Inertia-Version',
            hash_file('xxh128', public_path('build/manifest.json')),
        )
        ->get(route('patients.external_clinical_record', $patient))
        ->assertStatus(409)
        ->assertHeader(
            'X-Inertia-Location',
            'https://historia-clinica.example.test',
        );
});

it('denies unrelated users and audits the denied attempt', function () {
    $patient = Patient::factory()->create();
    $professional = Professional::factory()->create();

    $this->actingAs($professional->user)
        ->get(route('patients.external_clinical_record', $patient))
        ->assertForbidden();

    $audit = AuditLog::query()
        ->where('event', 'external_clinical_record.access_denied')
        ->sole();

    expect($audit->outcome)
        ->toBe('denied')
        ->and($audit->auditable_id)->toBe((string) $patient->getKey());
});

it('rejects a missing or insecure external URL', function (?string $url) {
    config()->set('services.external_clinical_record.url', $url);
    $patient = Patient::factory()->create();
    $professional = externalClinicalRecordProfessional($patient);

    $this->actingAs($professional->user)
        ->get(route('patients.external_clinical_record', $patient))
        ->assertServiceUnavailable();

    expect(AuditLog::query()
        ->where('event', 'external_clinical_record.configuration_failed')
        ->where('outcome', 'failure')
        ->exists())->toBeTrue();
})->with([
    'missing URL' => null,
    'non-HTTPS URL' => 'http://historia-clinica.example.test',
]);

it('contains no internal clinical schema', function () {
    expect(Schema::hasTable('clinical_records'))->toBeFalse()
        ->and(Schema::hasTable('clinical_record_addenda'))->toBeFalse()
        ->and(Schema::hasTable('clinical_record_attachments'))->toBeFalse()
        ->and(Schema::hasColumn('patients', 'clinical_alerts'))->toBeFalse();
});
