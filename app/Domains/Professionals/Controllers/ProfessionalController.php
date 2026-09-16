<?php

namespace App\Domains\Professionals\Controllers;

use App\Domains\Organizations\Enums\OrganizationStatus;
use App\Domains\Organizations\Models\Location;
use App\Domains\Professionals\Enums\OfficeAssignmentType;
use App\Domains\Professionals\Enums\ProfessionalStatus;
use App\Domains\Professionals\Models\Professional;
use App\Domains\Professionals\Models\ProfessionalSchedule;
use App\Domains\Professionals\Requests\StoreProfessionalRequest;
use App\Domains\Professionals\Requests\UpdateProfessionalRequest;
use App\Domains\Professionals\Services\ProfessionalService;
use App\Domains\Users\Enums\UserStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProfessionalController extends Controller
{
    public function __construct(
        private ProfessionalService $service,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Professional::class);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(ProfessionalStatus::class)],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);
        $filters = Arr::only($validated, ['search', 'status']);
        $paginationParameters = Arr::only($validated, ['search', 'status', 'per_page']);
        $professionals = $this->service
            ->paginate($filters, (int) ($validated['per_page'] ?? 15))
            ->appends($paginationParameters);

        return Inertia::render('professionals/views/Index', [
            'professionals' => $professionals->through(
                fn (Professional $professional): array => $this->professionalData($professional),
            ),
            'filters' => $filters,
            'summaries' => $this->service->summaries(),
            'statusOptions' => $this->statusOptions(),
            'officeAssignmentTypes' => $this->officeAssignmentTypes(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Professional::class);

        return Inertia::render('professionals/views/Create', [
            'statuses' => $this->statusOptions(),
            'statusOptions' => $this->statusOptions(),
            'officeAssignmentTypes' => $this->officeAssignmentTypes(),
            'locationOptions' => $this->locationOptions(),
        ]);
    }

    public function store(StoreProfessionalRequest $request): RedirectResponse
    {
        Gate::authorize('create', Professional::class);

        $result = $this->service->create($request->validated());

        Inertia::flash([
            'success' => 'El profesional fue creado correctamente.',
            'invitationUrl' => $result['invitation_url'],
        ]);

        return redirect()->route('professionals.index');
    }

    public function edit(Professional $professional): Response
    {
        Gate::authorize('update', $professional);

        $professional->loadMissing(['user', 'schedules.location']);

        return Inertia::render('professionals/views/Edit', [
            'professional' => $this->professionalData($professional),
            'statuses' => $this->statusOptions(),
            'statusOptions' => $this->statusOptions(),
            'officeAssignmentTypes' => $this->officeAssignmentTypes(),
            'locationOptions' => $this->locationOptions(),
        ]);
    }

    public function update(UpdateProfessionalRequest $request, Professional $professional): RedirectResponse
    {
        Gate::authorize('update', $professional);

        $result = $this->service->update($professional, $request->validated());
        $flash = ['success' => 'El profesional fue actualizado correctamente.'];

        if ($result['invitation_url'] !== null) {
            $flash['invitationUrl'] = $result['invitation_url'];
        }

        Inertia::flash($flash);

        return redirect()->route('professionals.index');
    }

    public function destroy(Professional $professional): RedirectResponse
    {
        Gate::authorize('delete', $professional);
        $this->service->deactivate($professional);
        Inertia::flash('success', 'El profesional fue inactivado.');

        return back();
    }

    public function restore(Professional $professional): RedirectResponse
    {
        Gate::authorize('restore', $professional);
        $result = $this->service->restore($professional);
        $flash = ['success' => 'El profesional fue reactivado.'];

        if ($result['invitation_url'] !== null) {
            $flash['invitationUrl'] = $result['invitation_url'];
        }

        Inertia::flash($flash);

        return back();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return array_map(
            fn (ProfessionalStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            ProfessionalStatus::cases(),
        );
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function officeAssignmentTypes(): array
    {
        return array_map(
            fn (OfficeAssignmentType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
            ],
            OfficeAssignmentType::cases(),
        );
    }

    /**
     * @return array<int, array{value: int, label: string, code: string}>
     */
    private function locationOptions(): array
    {
        return Location::query()
            ->select(['id', 'name', 'code'])
            ->where('status', OrganizationStatus::Active->value)
            ->whereNull('archived_at')
            ->orderBy('name')
            ->get()
            ->map(fn (Location $location): array => [
                'value' => (int) $location->getKey(),
                'label' => $location->name,
                'code' => $location->code,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function professionalData(Professional $professional): array
    {
        $professional->loadMissing(['user', 'schedules.location']);

        return [
            'id' => $professional->getKey(),
            'name' => $professional->user->name,
            'email' => $professional->user->email,
            'document_number' => $professional->document_number,
            'phone' => $professional->phone,
            'specialty' => $professional->specialty,
            'registration' => trim($professional->registration_type.' '.$professional->registration_number),
            'registration_type' => $professional->registration_type,
            'registration_number' => $professional->registration_number,
            'appointment_duration_minutes' => $professional->appointment_duration_minutes,
            'status' => [
                'value' => $professional->status->value,
                'label' => $professional->status->label(),
            ],
            'office_assignment_type' => [
                'value' => $professional->office_assignment_type->value,
                'label' => $professional->office_assignment_type->label(),
            ],
            'fixed_office' => $professional->fixed_office,
            'schedules' => $professional->schedules
                ->map(fn (ProfessionalSchedule $schedule): array => [
                    'id' => $schedule->getKey(),
                    'location_id' => $schedule->location_id,
                    'location_name' => $schedule->location?->name
                        ?? $schedule->location_name,
                    'weekday' => $schedule->weekday,
                    'weekday_label' => $this->weekdayLabel($schedule->weekday),
                    'start_time' => $schedule->start_time,
                    'end_time' => $schedule->end_time,
                ])
                ->values()
                ->all(),
            'invitation_pending' => $professional->user->status === UserStatus::PendingInvitation,
        ];
    }

    private function weekdayLabel(int $weekday): string
    {
        return match ($weekday) {
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
            7 => 'Domingo',
            default => "Día $weekday",
        };
    }
}
