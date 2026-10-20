<?php

namespace App\Domains\Patients\Controllers;

use App\Domains\Audits\Data\AuditContext;
use App\Domains\Audits\Services\AuditService;
use App\Domains\Patients\Enums\PatientStatus;
use App\Domains\Patients\Enums\RegisteredSex;
use App\Domains\Patients\Models\Patient;
use App\Domains\Patients\Models\PatientContact;
use App\Domains\Patients\Requests\ArchivePatientRequest;
use App\Domains\Patients\Requests\StorePatientRequest;
use App\Domains\Patients\Requests\UpdatePatientRequest;
use App\Domains\Patients\Services\PatientService;
use App\Domains\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PatientController extends Controller
{
    public function __construct(
        private PatientService $service,
        private AuditService $audit,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Patient::class);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(PatientStatus::class)],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);
        $filters = Arr::only($validated, ['search', 'status']);
        $paginationParameters = Arr::only($validated, ['search', 'status', 'per_page']);
        $patients = $this->service
            ->paginate($filters, (int) ($validated['per_page'] ?? 15))
            ->appends($paginationParameters);
        $actor = $this->authenticatedUser($request);
        $this->audit->recordResource(
            event: 'patient.index.viewed',
            auditableType: Patient::class,
            afterValues: [
                'filters' => $filters,
                'page' => max($request->integer('page', 1), 1),
                'per_page' => (int) ($validated['per_page'] ?? 15),
            ],
            actor: $actor,
            context: $this->auditContext($request),
        );

        return Inertia::render('patients/views/Index', [
            'patients' => $patients->through(
                fn (Patient $patient): array => $this->patientIndexData($patient, $actor),
            ),
            'filters' => $filters,
            'summaries' => $this->service->summaries(),
            'statusOptions' => $this->statusOptions(PatientStatus::cases()),
            'registeredSexOptions' => $this->registeredSexOptions(),
            'permissions' => $this->globalPermissions($actor),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Patient::class);
        $actor = $this->authenticatedUser($request);

        return Inertia::render('patients/views/Create', [
            'statusOptions' => $this->formStatusOptions($actor),
            'registeredSexOptions' => $this->registeredSexOptions(),
            'permissions' => $this->globalPermissions($actor),
        ]);
    }

    public function store(StorePatientRequest $request): RedirectResponse
    {
        Gate::authorize('create', Patient::class);
        $actor = $this->authenticatedUser($request);
        $result = $this->service->create(
            $request->validated(),
            $actor,
            $this->auditContext($request),
        );
        Inertia::flash([
            'success' => 'El paciente fue creado y su invitación fue enviada por correo.',
            'invitationUrl' => $result->invitationUrl,
        ]);

        return redirect()->route('patients.index');
    }

    public function show(Request $request, Patient $patient): Response
    {
        Gate::authorize('view', $patient);
        $actor = $this->authenticatedUser($request);
        $patient = $this->loadPatientContacts($patient);
        $this->audit->record(
            event: 'patient.viewed',
            auditable: $patient,
            actor: $actor,
            context: $this->auditContext($request),
        );
        $props = [
            'patient' => $this->patientData($patient, $actor),
            'permissions' => $this->patientPermissions($actor, $patient),
        ];

        if (Gate::forUser($actor)->allows('accessExternalClinicalRecord', $patient)) {
            $props['externalClinicalRecordUrl'] = route(
                'patients.external_clinical_record',
                $patient,
            );
        }

        return Inertia::render('patients/views/Show', $props);
    }

    public function edit(Request $request, Patient $patient): Response
    {
        Gate::authorize('update', $patient);
        $actor = $this->authenticatedUser($request);
        $patient = $this->loadPatientContacts($patient);
        $this->audit->record(
            event: 'patient.edit.viewed',
            auditable: $patient,
            actor: $actor,
            context: $this->auditContext($request),
        );
        return Inertia::render('patients/views/Edit', [
            'patient' => $this->patientData($patient, $actor),
            'statusOptions' => $this->formStatusOptions($actor, $patient),
            'registeredSexOptions' => $this->registeredSexOptions(),
            'permissions' => $this->patientPermissions($actor, $patient),
        ]);
    }

    public function update(
        UpdatePatientRequest $request,
        Patient $patient,
    ): RedirectResponse {
        Gate::authorize('update', $patient);
        $actor = $this->authenticatedUser($request);
        $patient = $this->service->update(
            $patient,
            $request->validated(),
            $actor,
            $this->auditContext($request),
        );
        Inertia::flash('success', 'El paciente fue actualizado correctamente.');

        return redirect()->route('patients.show', $patient);
    }

    public function destroy(ArchivePatientRequest $request, Patient $patient): RedirectResponse
    {
        $actor = $this->authenticatedUser($request);
        $this->service->archive(
            $patient,
            (bool) $request->validated('cancel_future_appointments', false),
            $actor,
            $this->auditContext($request),
        );
        Inertia::flash('success', 'El paciente fue archivado.');

        return redirect()->route('patients.index');
    }

    public function restore(Request $request, Patient $patient): RedirectResponse
    {
        Gate::authorize('restore', $patient);
        $actor = $this->authenticatedUser($request);
        $this->service->restore(
            $patient,
            $actor,
            $this->auditContext($request),
        );
        Inertia::flash('success', 'El paciente fue restaurado.');

        return redirect()->route('patients.index');
    }

    /**
     * @param  array<int, PatientStatus>  $statuses
     * @return array<int, array{value: string, label: string}>
     */
    private function statusOptions(array $statuses): array
    {
        return array_map(
            fn (PatientStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            $statuses,
        );
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function formStatusOptions(User $actor, ?Patient $patient = null): array
    {
        if (! Gate::forUser($actor)->allows('manageStatus', Patient::class)) {
            return $this->statusOptions([
                $patient?->status ?? PatientStatus::Active,
            ]);
        }

        return $this->statusOptions(PatientStatus::editableCases());
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function registeredSexOptions(): array
    {
        return array_map(
            fn (RegisteredSex $registeredSex): array => [
                'value' => $registeredSex->value,
                'label' => $registeredSex->label(),
            ],
            RegisteredSex::cases(),
        );
    }

    /**
     * @return array<string, bool>
     */
    private function globalPermissions(User $actor): array
    {
        $canManageAdministrativeData = Gate::forUser($actor)->allows(
            'create',
            Patient::class,
        );
        $canManageStatus = Gate::forUser($actor)->allows(
            'manageStatus',
            Patient::class,
        );
        return [
            'create' => $canManageAdministrativeData,
            'update' => $canManageAdministrativeData,
            'archive' => $canManageStatus,
            'restore' => $canManageStatus,
            'manage_status' => $canManageStatus,
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function patientPermissions(User $actor, Patient $patient): array
    {
        return [
            'view' => Gate::forUser($actor)->allows('view', $patient),
            'update' => Gate::forUser($actor)->allows('update', $patient),
            'archive' => Gate::forUser($actor)->allows('delete', $patient),
            'restore' => Gate::forUser($actor)->allows('restore', $patient),
            'manage_status' => Gate::forUser($actor)->allows(
                'manageStatus',
                $patient,
            ),
            'access_external_clinical_record' => Gate::forUser($actor)->allows(
                'accessExternalClinicalRecord',
                $patient,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function patientIndexData(Patient $patient, User $actor): array
    {
        return [
            'id' => $patient->getKey(),
            'document_number' => $patient->document_number,
            'first_name' => $patient->first_name,
            'last_name' => $patient->last_name,
            'display_name' => trim($patient->first_name.' '.$patient->last_name),
            'birth_date' => $patient->birth_date->toDateString(),
            'age' => $patient->birth_date->age,
            'phone' => $patient->phone,
            'coverage_name' => $patient->coverage_name,
            'member_number' => $patient->member_number,
            'status' => [
                'value' => $patient->status->value,
                'label' => $patient->status->label(),
            ],
            'archived_at' => $patient->archived_at?->toISOString(),
            'updated_at' => $patient->updated_at->toISOString(),
            'contacts_count' => (int) $patient->contacts_count,
            'permissions' => $this->patientPermissions($actor, $patient),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function patientData(Patient $patient, User $actor): array
    {
        return [
            ...$this->patientIndexData($patient, $actor),
            'lock_version' => $patient->lock_version,
            'registered_sex' => [
                'value' => $patient->registered_sex->value,
                'label' => $patient->registered_sex->label(),
            ],
            'gender' => $patient->gender,
            'email' => $patient->email,
            'address' => $patient->address,
            'contacts' => $patient->contacts
                ->map(fn (PatientContact $contact): array => [
                    'id' => $contact->getKey(),
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

    private function loadPatientContacts(Patient $patient): Patient
    {
        return $patient->load([
            'contacts' => fn ($query) => $query
                ->orderByDesc('is_legal_representative')
                ->orderByDesc('is_emergency_contact')
                ->orderBy('full_name'),
        ])->loadCount('contacts');
    }

    private function auditContext(Request $request): AuditContext
    {
        $correlationId = $request->header('X-Request-ID');

        return new AuditContext(
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            correlationId: is_string($correlationId)
                ? $correlationId
                : null,
        );
    }

    private function authenticatedUser(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }
}
