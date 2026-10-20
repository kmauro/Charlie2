<?php

namespace App\Domains\Patients\Services;

use App\Domains\Audits\Data\AuditContext;
use App\Domains\Audits\Services\AuditService;
use App\Domains\Appointments\Enums\AppointmentStatus;
use App\Domains\Appointments\Models\Appointment;
use App\Domains\Patients\Data\PatientCreationResult;
use App\Domains\Patients\Enums\PatientStatus;
use App\Domains\Patients\Models\Patient;
use App\Domains\Patients\Repositories\Contracts\PatientRepository;
use App\Domains\Users\Models\User;
use App\Domains\Users\Services\UserService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PatientService
{
    public function __construct(
        private PatientRepository $patients,
        private AuditService $audit,
        private UserService $users,
    ) {}

    /**
     * @param  array{search?: string, status?: string}  $filters
     * @return LengthAwarePaginator<int, Patient>
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $normalizedFilters = Arr::only($filters, ['search', 'status']);

        if (isset($normalizedFilters['search'])) {
            $normalizedFilters['search'] = Str::squish($normalizedFilters['search']);
        }

        return $this->patients->paginate(
            array_filter(
                $normalizedFilters,
                fn (mixed $value): bool => $value !== null && $value !== '',
            ),
            min(max($perPage, 1), 100),
        );
    }

    public function find(int $id): Patient
    {
        return $this->patients->findOrFail($id);
    }

    /**
     * @return array{total: int, active: int, inpatient: int, archived: int, deceased: int, blocked: int}
     */
    public function summaries(): array
    {
        return $this->patients->summaries();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(
        array $data,
        User $actor,
        AuditContext $auditContext,
    ): PatientCreationResult {
        return DB::transaction(function () use ($data, $actor, $auditContext): PatientCreationResult {
            $invitation = $this->users->invitePatient([
                'name' => Str::squish($data['first_name'].' '.$data['last_name']),
                'email' => $data['email'],
            ]);
            $patientAttributes = $this->patientAttributes($data);
            $patientAttributes['user_id'] = $invitation->user->getKey();

            $patient = $this->patients->create(
                $patientAttributes,
                $this->contactAttributes($data['contacts'] ?? []),
            );

            $this->audit->record(
                event: 'patient.created',
                auditable: $patient,
                afterValues: $this->patientAuditSnapshot($patient),
                actor: $actor,
                context: $auditContext,
            );

            return new PatientCreationResult(
                patient: $patient,
                invitationUrl: $invitation->invitationUrl,
            );
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(
        Patient $patient,
        array $data,
        User $actor,
        AuditContext $auditContext,
    ): Patient {
        return DB::transaction(function () use (
            $patient,
            $data,
            $actor,
            $auditContext,
        ): Patient {
            $lockedPatient = $this->patients->lockForUpdate($patient);

            if ($lockedPatient->lock_version !== (int) $data['lock_version']) {
                throw ValidationException::withMessages([
                    'lock_version' => 'La ficha fue modificada por otro usuario. Recargá la página antes de volver a guardar.',
                ]);
            }

            if ($lockedPatient->isArchived()) {
                throw ValidationException::withMessages([
                    'status' => 'El paciente debe restaurarse antes de poder modificarlo.',
                ]);
            }

            $attributes = $this->patientAttributes($data, $lockedPatient->status);
            $attributes['lock_version'] = $lockedPatient->lock_version + 1;

            if ($attributes['status'] !== $lockedPatient->status) {
                $attributes['portal_access_until'] = $this->portalAccessUntil(
                    $attributes['status'],
                );
            }

            $beforeValues = $this->patientAuditSnapshot($lockedPatient);
            $statusChanged = $lockedPatient->status !== $attributes['status'];
            $updatedPatient = $this->patients->update(
                $lockedPatient,
                $attributes,
                $this->contactAttributes($data['contacts'] ?? []),
            );
            $afterValues = $this->patientAuditSnapshot($updatedPatient);

            if ($statusChanged) {
                $stateKeys = ['status', 'archived_at', 'previous_status'];
                $beforeValues = Arr::except($beforeValues, $stateKeys);
                $afterValues = Arr::except($afterValues, $stateKeys);
            }

            if ($updatedPatient->user !== null) {
                $this->users->updatePatientIdentity($updatedPatient->user, [
                    'name' => trim($updatedPatient->first_name.' '.$updatedPatient->last_name),
                    'email' => $updatedPatient->email,
                ]);
            }

            $this->audit->record(
                event: 'patient.updated',
                auditable: $updatedPatient,
                beforeValues: $beforeValues,
                afterValues: $afterValues,
                actor: $actor,
                context: $auditContext,
            );

            return $updatedPatient;
        }, 3);
    }

    public function archive(
        Patient $patient,
        bool $cancelFutureAppointments,
        User $actor,
        AuditContext $auditContext,
    ): Patient {
        return DB::transaction(function () use (
            $patient,
            $cancelFutureAppointments,
            $actor,
            $auditContext,
        ): Patient {
            $lockedPatient = $this->patients->lockForUpdate($patient);

            if ($lockedPatient->isArchived()) {
                throw ValidationException::withMessages([
                    'status' => 'El paciente ya se encuentra archivado.',
                ]);
            }

            $archivedPatient = $this->patients->setStatus(
                $lockedPatient,
                PatientStatus::Archived,
                now(),
                $lockedPatient->status,
                now()->addMonthsNoOverflow(2),
            );

            if ($cancelFutureAppointments) {
                Appointment::query()
                    ->whereBelongsTo($lockedPatient)
                    ->where('starts_at', '>=', now())
                    ->whereIn('status', [
                        AppointmentStatus::Reserved->value,
                        AppointmentStatus::Confirmed->value,
                        AppointmentStatus::Waiting->value,
                        AppointmentStatus::Present->value,
                    ])
                    ->update([
                        'status' => AppointmentStatus::Cancelled->value,
                        'cancellation_reason' => 'Cancelado al archivar al paciente.',
                        'cancelled_at' => now(),
                        'cancelled_by' => $actor->getKey(),
                        'lock_version' => DB::raw('lock_version + 1'),
                    ]);
            }

            $this->audit->record(
                event: 'patient.archived',
                auditable: $archivedPatient,
                actor: $actor,
                context: $auditContext,
            );

            return $archivedPatient;
        }, 3);
    }

    public function restore(
        Patient $patient,
        User $actor,
        AuditContext $auditContext,
    ): Patient {
        return DB::transaction(function () use (
            $patient,
            $actor,
            $auditContext,
        ): Patient {
            $lockedPatient = $this->patients->lockForUpdate($patient);

            if (! $lockedPatient->isArchived()) {
                throw ValidationException::withMessages([
                    'status' => 'Sólo se pueden restaurar pacientes archivados.',
                ]);
            }

            $restoredStatus = $lockedPatient->previous_status;

            if ($restoredStatus === null
                || $restoredStatus === PatientStatus::Archived) {
                $restoredStatus = PatientStatus::Active;
            }

            $restoredPatient = $this->patients->setStatus(
                $lockedPatient,
                $restoredStatus,
                null,
                null,
                $this->portalAccessUntil($restoredStatus),
            );

            $this->audit->record(
                event: 'patient.restored',
                auditable: $restoredPatient,
                actor: $actor,
                context: $auditContext,
            );

            return $restoredPatient;
        }, 3);
    }

    /** @param array{phone?: string|null, email: string, address?: string|null, appointment_reminders_by_email?: bool, appointment_reminders_by_whatsapp?: bool, appointment_reminder_hours?: int} $data */
    public function updatePortalProfile(
        Patient $patient,
        array $data,
        User $actor,
        AuditContext $auditContext,
    ): Patient {
        return DB::transaction(function () use ($patient, $data, $actor, $auditContext): Patient {
            $lockedPatient = $this->patients->lockForUpdate($patient);

            abort_unless($lockedPatient->user_id === $actor->getKey() && $lockedPatient->canAccessPortal(), 403);

            $beforeValues = Arr::only($this->patientAuditSnapshot($lockedPatient), [
                'phone',
                'email',
                'address',
            ]);
            $lockedPatient->update([
                'phone' => $this->nullableTrimmedString($data['phone'] ?? null),
                'email' => Str::lower(trim($data['email'])),
                'address' => $this->nullableSquishedString($data['address'] ?? null),
                'lock_version' => $lockedPatient->lock_version + 1,
            ]);
            $this->users->updatePatientIdentity($actor, [
                'name' => trim($lockedPatient->first_name.' '.$lockedPatient->last_name),
                'email' => $lockedPatient->email,
            ]);
            $lockedPatient->notificationPreference()->updateOrCreate([], [
                'appointment_reminders_by_email' => (bool) ($data['appointment_reminders_by_email'] ?? true),
                'appointment_reminders_by_whatsapp' => (bool) ($data['appointment_reminders_by_whatsapp'] ?? false),
                'appointment_reminder_hours' => (int) ($data['appointment_reminder_hours'] ?? 24),
            ]);
            $updatedPatient = $this->patients->findOrFail((int) $lockedPatient->getKey());

            $this->audit->record(
                event: 'patient.portal_profile_updated',
                auditable: $updatedPatient,
                beforeValues: $beforeValues,
                afterValues: Arr::only($this->patientAuditSnapshot($updatedPatient), [
                    'phone',
                    'email',
                    'address',
                ]),
                actor: $actor,
                context: $auditContext,
            );

            return $updatedPatient;
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function patientAttributes(
        array $data,
        ?PatientStatus $currentStatus = null,
    ): array {
        $status = PatientStatus::from(
            (string) ($data['status'] ?? $currentStatus?->value ?? PatientStatus::Active->value),
        );

        if ($status === PatientStatus::Archived) {
            throw ValidationException::withMessages([
                'status' => 'El archivado debe realizarse mediante la acción correspondiente.',
            ]);
        }

        $coverageName = Str::squish($data['coverage_name']);

        $attributes = [
            'document_number' => (string) preg_replace(
                '/\D+/',
                '',
                (string) $data['document_number'],
            ),
            'first_name' => Str::squish($data['first_name']),
            'last_name' => Str::squish($data['last_name']),
            'birth_date' => $data['birth_date'],
            'registered_sex' => $data['registered_sex'],
            'gender' => $this->nullableSquishedString($data['gender'] ?? null),
            'phone' => $this->nullableTrimmedString($data['phone'] ?? null),
            'email' => filled($data['email'] ?? null)
                ? Str::lower(trim($data['email']))
                : null,
            'address' => $this->nullableSquishedString($data['address'] ?? null),
            'coverage_name' => $coverageName,
            'member_number' => Str::lower($coverageName) === 'particular'
                ? null
                : $this->nullableTrimmedString($data['member_number'] ?? null),
            'status' => $status,
        ];

        if ($currentStatus === null) {
            $attributes['archived_at'] = null;
        }

        return $attributes;
    }

    /**
     * @param  array<int, array<string, mixed>>  $contacts
     * @return array<int, array<string, mixed>>
     */
    private function contactAttributes(array $contacts): array
    {
        return array_map(
            fn (array $contact): array => [
                'full_name' => Str::squish($contact['full_name']),
                'relationship' => Str::squish($contact['relationship']),
                'phone' => $this->nullableTrimmedString($contact['phone'] ?? null),
                'email' => filled($contact['email'] ?? null)
                    ? Str::lower(trim($contact['email']))
                    : null,
                'is_emergency_contact' => (bool) ($contact['is_emergency_contact'] ?? false),
                'is_legal_representative' => (bool) ($contact['is_legal_representative'] ?? false),
                'is_financially_responsible' => (bool) ($contact['is_financially_responsible'] ?? false),
            ],
            $contacts,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function patientAuditSnapshot(Patient $patient): array
    {
        return [
            'document_number' => $patient->document_number,
            'first_name' => $patient->first_name,
            'last_name' => $patient->last_name,
            'birth_date' => $patient->birth_date->toDateString(),
            'registered_sex' => $patient->registered_sex->value,
            'gender' => $patient->gender,
            'phone' => $patient->phone,
            'email' => $patient->email,
            'address' => $patient->address,
            'coverage_name' => $patient->coverage_name,
            'member_number' => $patient->member_number,
            'status' => $patient->status->value,
            'archived_at' => $patient->archived_at?->toISOString(),
            'previous_status' => $patient->previous_status?->value,
            'lock_version' => $patient->lock_version,
            'contacts' => $patient->contacts
                ->map(fn ($contact): array => [
                    'full_name' => $contact->full_name,
                    'relationship' => $contact->relationship,
                    'phone' => $contact->phone,
                    'email' => $contact->email,
                    'is_emergency_contact' => $contact->is_emergency_contact,
                    'is_legal_representative' => $contact->is_legal_representative,
                    'is_financially_responsible' => $contact->is_financially_responsible,
                ])
                ->values()
                ->all(),
        ];
    }

    private function nullableSquishedString(mixed $value): ?string
    {
        return filled($value) ? Str::squish((string) $value) : null;
    }

    private function nullableTrimmedString(mixed $value): ?string
    {
        return filled($value) ? trim((string) $value) : null;
    }

    private function portalAccessUntil(PatientStatus $status): ?\DateTimeInterface
    {
        return in_array($status, [PatientStatus::Active, PatientStatus::Inpatient], true)
            ? null
            : now()->addMonthsNoOverflow(2);
    }
}
