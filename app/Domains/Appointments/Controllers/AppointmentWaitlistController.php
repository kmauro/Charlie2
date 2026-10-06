<?php

namespace App\Domains\Appointments\Controllers;

use App\Domains\Appointments\Enums\AppointmentType;
use App\Domains\Appointments\Models\Appointment;
use App\Domains\Appointments\Models\AppointmentWaitlistEntry;
use App\Domains\Appointments\Requests\StoreAppointmentRequest;
use App\Domains\Appointments\Requests\StoreWaitlistEntryRequest;
use App\Domains\Appointments\Services\AppointmentService;
use App\Domains\Audits\Data\AuditContext;
use App\Domains\Audits\Services\AuditService;
use App\Domains\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentWaitlistController extends Controller
{
    public function __construct(private AppointmentService $service, private AuditService $audit) {}

    public function index(Request $request): Response
    {
        Gate::authorize('manageWaitlist', Appointment::class);
        $actor = $this->actor($request);
        $entries = AppointmentWaitlistEntry::query()->with(['patient:id,first_name,last_name,document_number', 'professional.user:id,name', 'location:id,name,code'])
            ->where('status', 'waiting')->orderByDesc('priority')->oldest()->get();

        return Inertia::render('appointments/views/Waitlist', [
            'entries' => $entries->map(fn (AppointmentWaitlistEntry $entry): array => [
                'id' => $entry->id, 'patient' => trim($entry->patient->last_name.', '.$entry->patient->first_name),
                'document_number' => $entry->patient->document_number, 'professional' => $entry->professional?->user?->name,
                'location' => $entry->location?->name, 'type' => AppointmentType::from($entry->type)->label(),
                'desired_from' => $entry->desired_from?->format('Y-m-d'), 'priority' => $entry->priority, 'notes' => $entry->notes,
                'schedule_url' => route('appointments.waitlist.schedule.create', $entry),
            ])->all(),
            'patientOptions' => $this->service->availablePatients()->map(fn ($patient): array => ['value' => $patient->id, 'label' => trim($patient->last_name.', '.$patient->first_name), 'document_number' => $patient->document_number])->all(),
            'professionalOptions' => $this->service->activeProfessionals()->map(fn ($professional): array => ['value' => $professional->id, 'label' => $professional->user->name, 'specialty' => $professional->specialty])->all(),
            'locationOptions' => $this->service->activeLocations()->map(fn ($location): array => ['value' => $location->id, 'label' => $location->name])->all(),
            'typeOptions' => array_map(fn (AppointmentType $type): array => ['value' => $type->value, 'label' => $type->label()], AppointmentType::cases()),
            'storeUrl' => route('appointments.waitlist.store'),
        ]);
    }

    public function store(StoreWaitlistEntryRequest $request): RedirectResponse
    {
        Gate::authorize('manageWaitlist', Appointment::class);
        $entry = AppointmentWaitlistEntry::query()->create($request->validated());
        $this->audit->record(event: 'appointment.waitlist_created', auditable: $entry, actor: $this->actor($request), context: $this->context($request));
        Inertia::flash('success', 'La solicitud fue incorporada a la lista de espera.');

        return back();
    }

    public function createSchedule(Request $request, AppointmentWaitlistEntry $waitlistEntry): Response
    {
        Gate::authorize('manageWaitlist', Appointment::class);
        abort_unless($waitlistEntry->status === 'waiting', 404);

        $initialValues = [
            'patient_id' => (int) $waitlistEntry->patient_id,
            'professional_id' => $waitlistEntry->professional_id,
            'location_id' => $waitlistEntry->location_id,
            'type' => $waitlistEntry->type,
            'reason' => $waitlistEntry->notes,
            ...($this->service->nextAvailableWaitlistSlot($waitlistEntry) ?? []),
        ];

        return Inertia::render('appointments/views/Create', [...$this->appointmentFormOptions($this->actor($request)), 'initialValues' => $initialValues, 'urls' => ['index' => route('appointments.waitlist.index'), 'store' => route('appointments.waitlist.schedule.store', $waitlistEntry)]]);
    }

    public function schedule(StoreAppointmentRequest $request, AppointmentWaitlistEntry $waitlistEntry): RedirectResponse
    {
        Gate::authorize('manageWaitlist', Appointment::class);
        $this->service->createFromWaitlist($waitlistEntry, $request->validated(), $this->actor($request), $this->context($request));
        Inertia::flash('success', 'El turno fue asignado y la solicitud salió de la lista de espera.');

        return redirect()->route('appointments.waitlist.index');
    }

    /** @return array<string, mixed> */
    private function appointmentFormOptions(User $actor): array
    {
        $scope = $this->service->professionalScopeFor($actor);
        $locations = $this->service->locationScopeFor($actor);

        return [
            'patientOptions' => $this->service->availablePatients()->map(fn ($patient): array => ['value' => $patient->id, 'label' => trim($patient->last_name.', '.$patient->first_name), 'document_number' => $patient->document_number])->all(),
            'professionalOptions' => $this->service->activeProfessionals($scope, $locations)->map(fn ($professional): array => [
                'value' => $professional->id, 'label' => $professional->user->name,
                'specialty' => $professional->specialty, 'duration_minutes' => $professional->appointment_duration_minutes,
                'schedules' => $professional->schedules->map(fn ($schedule): array => [
                    'location_id' => $schedule->location_id, 'weekday' => $schedule->weekday,
                    'start_time' => $schedule->start_time, 'end_time' => $schedule->end_time,
                    'location_name' => $schedule->location_name,
                    'breaks' => $schedule->breaks->map(fn ($break): array => ['start_time' => $break->start_time, 'end_time' => $break->end_time, 'label' => $break->label])->all(),
                ])->all(),
            ])->all(),
            'locationOptions' => $this->service->activeLocations($locations)->map(fn ($location): array => ['value' => $location->id, 'label' => $location->name, 'code' => $location->code])->all(),
            'roomOptions' => $this->service->activeConsultingRooms($locations)->map(fn ($room): array => ['value' => $room->id, 'location_id' => $room->location_id, 'label' => $room->name, 'code' => $room->code, 'sector' => $room->sector])->all(),
            'agendaConstraints' => $this->service->agendaConstraints($scope, $locations),
            'typeOptions' => array_map(fn (AppointmentType $type): array => ['value' => $type->value, 'label' => $type->label()], AppointmentType::cases()),
            'statusOptions' => [],
            'allowOverbook' => true,
        ];
    }

    private function context(Request $request): AuditContext
    {
        return new AuditContext(ipAddress: $request->ip(), userAgent: $request->userAgent(), correlationId: $request->header('X-Request-ID'));
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
