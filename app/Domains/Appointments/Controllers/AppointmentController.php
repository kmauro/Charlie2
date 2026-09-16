<?php

namespace App\Domains\Appointments\Controllers;

use App\Domains\Appointments\Enums\AppointmentStatus;
use App\Domains\Appointments\Enums\AppointmentType;
use App\Domains\Appointments\Models\Appointment;
use App\Domains\Appointments\Requests\CancelAppointmentRequest;
use App\Domains\Appointments\Requests\ChangeAppointmentStatusRequest;
use App\Domains\Appointments\Requests\StoreAppointmentRequest;
use App\Domains\Appointments\Requests\UpdateAppointmentRequest;
use App\Domains\Appointments\Services\AppointmentService;
use App\Domains\Audits\Data\AuditContext;
use App\Domains\Audits\Services\AuditService;
use App\Domains\Organizations\Models\Location;
use App\Domains\Patients\Models\Patient;
use App\Domains\Professionals\Models\Professional;
use App\Domains\Professionals\Models\ProfessionalSchedule;
use App\Domains\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentController extends Controller
{
    public function __construct(
        private AppointmentService $service,
        private AuditService $audit,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Appointment::class);
        $actor = $this->authenticatedUser($request);
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', Rule::enum(AppointmentStatus::class)],
            'type' => ['nullable', Rule::enum(AppointmentType::class)],
            'location_id' => ['nullable', 'integer'],
            'professional_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);
        $filters = Arr::only($validated, [
            'date',
            'status',
            'type',
            'location_id',
            'professional_id',
            'search',
        ]);
        $professionalScope = $this->service->professionalScopeFor($actor);
        $appointments = $this->service
            ->paginate(
                $filters,
                (int) ($validated['per_page'] ?? 15),
                $professionalScope,
            )
            ->appends($validated);

        $this->audit->recordResource(
            event: 'appointment.index.viewed',
            auditableType: Appointment::class,
            actor: $actor,
            context: $this->auditContext($request),
        );

        return Inertia::render('appointments/views/Index', [
            'appointments' => $appointments->through(
                fn (Appointment $appointment): array => $this->appointmentData(
                    $appointment,
                    $actor,
                ),
            ),
            'filters' => $filters,
            'summaries' => $this->service->summaries($professionalScope),
            'statusOptions' => $this->statusOptions(),
            'typeOptions' => $this->typeOptions(),
            'locationOptions' => $this->locationOptions(),
            'professionalOptions' => $this->professionalOptions($professionalScope),
            'permissions' => [
                'create' => $actor->can('create', Appointment::class),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Appointment::class);

        return Inertia::render('appointments/views/Create', [
            ...$this->formOptions(),
            'urls' => [
                'index' => route('appointments.index'),
                'store' => route('appointments.store'),
            ],
        ]);
    }

    public function store(StoreAppointmentRequest $request): RedirectResponse
    {
        $actor = $this->authenticatedUser($request);

        $this->service->create(
            $request->validated(),
            $actor,
            $this->auditContext($request),
        );
        Inertia::flash('success', 'El turno fue creado correctamente.');

        return redirect()->route('appointments.index');
    }

    public function edit(Request $request, Appointment $appointment): Response
    {
        Gate::authorize('update', $appointment);
        $actor = $this->authenticatedUser($request);
        $appointment = $this->service->find((int) $appointment->getKey());

        $this->audit->record(
            event: 'appointment.edit.viewed',
            auditable: $appointment,
            actor: $actor,
            context: $this->auditContext($request),
        );

        return Inertia::render('appointments/views/Edit', [
            'appointment' => $this->appointmentData($appointment, $actor),
            ...$this->formOptions(),
            'urls' => [
                'index' => route('appointments.index'),
                'update' => route('appointments.update', $appointment),
            ],
        ]);
    }

    public function update(
        UpdateAppointmentRequest $request,
        Appointment $appointment,
    ): RedirectResponse {
        $actor = $this->authenticatedUser($request);

        $this->service->update(
            $appointment,
            $request->validated(),
            $actor,
            $this->auditContext($request),
        );
        Inertia::flash('success', 'El turno fue actualizado correctamente.');

        return redirect()->route('appointments.index');
    }

    public function updateStatus(
        ChangeAppointmentStatusRequest $request,
        Appointment $appointment,
    ): RedirectResponse {
        $actor = $this->authenticatedUser($request);

        $this->service->changeStatus(
            $appointment,
            AppointmentStatus::from($request->validated('status')),
            (int) $request->validated('lock_version'),
            $actor,
            $this->auditContext($request),
        );
        Inertia::flash('success', 'El estado del turno fue actualizado.');

        return back();
    }

    public function cancel(
        CancelAppointmentRequest $request,
        Appointment $appointment,
    ): RedirectResponse {
        $actor = $this->authenticatedUser($request);

        $this->service->cancel(
            $appointment,
            (string) $request->validated('cancellation_reason'),
            (int) $request->validated('lock_version'),
            $actor,
            $this->auditContext($request),
        );
        Inertia::flash('success', 'El turno fue cancelado.');

        return back();
    }

    /** @return array<string, mixed> */
    private function formOptions(): array
    {
        return [
            'patientOptions' => $this->patientOptions(),
            'professionalOptions' => $this->professionalOptions(),
            'locationOptions' => $this->locationOptions(),
            'typeOptions' => $this->typeOptions(),
            'statusOptions' => $this->statusOptions(),
        ];
    }

    /** @return array<int, array{value: int, label: string, document_number: string}> */
    private function patientOptions(): array
    {
        return $this->service->availablePatients()
            ->map(fn (Patient $patient): array => [
                'value' => (int) $patient->getKey(),
                'label' => trim($patient->last_name.', '.$patient->first_name),
                'document_number' => $patient->document_number,
            ])
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function professionalOptions(?int $professionalId = null): array
    {
        return $this->service->activeProfessionals($professionalId)
            ->map(fn (Professional $professional): array => [
                'value' => (int) $professional->getKey(),
                'label' => $professional->user->name,
                'specialty' => $professional->specialty,
                'duration_minutes' => $professional->appointment_duration_minutes,
                'schedules' => $professional->schedules
                    ->map(fn (ProfessionalSchedule $schedule): array => [
                        'location_id' => $schedule->location_id,
                        'weekday' => $schedule->weekday,
                        'start_time' => $schedule->start_time,
                        'end_time' => $schedule->end_time,
                        'location_name' => $schedule->location_name,
                    ])
                    ->values()
                    ->all(),
            ])
            ->all();
    }

    /** @return array<int, array{value: int, label: string, code: string}> */
    private function locationOptions(): array
    {
        return $this->service->activeLocations()
            ->map(fn (Location $location): array => [
                'value' => (int) $location->getKey(),
                'label' => $location->name,
                'code' => $location->code,
            ])
            ->all();
    }

    /** @return array<int, array{value: string, label: string}> */
    private function statusOptions(): array
    {
        return array_map(
            fn (AppointmentStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            AppointmentStatus::cases(),
        );
    }

    /** @return array<int, array{value: string, label: string}> */
    private function typeOptions(): array
    {
        return array_map(
            fn (AppointmentType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
            ],
            AppointmentType::cases(),
        );
    }

    /** @return array<string, mixed> */
    private function appointmentData(Appointment $appointment, User $actor): array
    {
        $canUpdate = $actor->can('update', $appointment);
        $canChangeStatus = $actor->can('changeStatus', $appointment);
        $canCancel = $actor->can('cancel', $appointment)
            && $appointment->status->isCancellable()
            && $appointment->starts_at->gte(now()->addHours(24));

        return [
            'id' => (int) $appointment->getKey(),
            'starts_at' => $appointment->starts_at->format('Y-m-d\TH:i'),
            'ends_at' => $appointment->ends_at->format('Y-m-d\TH:i'),
            'duration_minutes' => $appointment->duration_minutes,
            'patient' => [
                'id' => (int) $appointment->patient->getKey(),
                'name' => trim($appointment->patient->last_name.', '.$appointment->patient->first_name),
                'document_number' => $appointment->patient->document_number,
            ],
            'professional' => [
                'id' => (int) $appointment->professional->getKey(),
                'name' => $appointment->professional->user->name,
                'specialty' => $appointment->professional->specialty,
            ],
            'location' => [
                'id' => (int) $appointment->location->getKey(),
                'name' => $appointment->location->name,
                'code' => $appointment->location->code,
            ],
            'status' => [
                'value' => $appointment->status->value,
                'label' => $appointment->status->label(),
            ],
            'type' => [
                'value' => $appointment->type->value,
                'label' => $appointment->type->label(),
            ],
            'office' => $appointment->office,
            'reason' => $appointment->reason,
            'is_overbook' => false,
            'cancellation' => $appointment->cancelled_at === null
                ? null
                : [
                    'reason' => $appointment->cancellation_reason,
                    'at' => $appointment->cancelled_at->toISOString(),
                    'by_name' => $appointment->cancelledBy?->name,
                ],
            'lock_version' => $appointment->lock_version,
            'available_statuses' => $canChangeStatus
                ? array_map(
                    fn (AppointmentStatus $status): array => [
                        'value' => $status->value,
                        'label' => $status->label(),
                    ],
                    $appointment->status->availableTransitions(),
                )
                : [],
            'permissions' => [
                'update' => $canUpdate,
                'change_status' => $canChangeStatus,
                'cancel' => $canCancel,
            ],
            'urls' => [
                'edit' => $canUpdate
                    ? route('appointments.edit', $appointment)
                    : null,
                'status' => $canChangeStatus
                    ? route('appointments.status', $appointment)
                    : null,
                'cancel' => $canCancel
                    ? route('appointments.cancel', $appointment)
                    : null,
            ],
        ];
    }

    private function auditContext(Request $request): AuditContext
    {
        $correlationId = $request->header('X-Request-ID');

        return new AuditContext(
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            correlationId: is_string($correlationId) ? $correlationId : null,
        );
    }

    private function authenticatedUser(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }
}
