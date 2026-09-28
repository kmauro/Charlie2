<?php

namespace App\Domains\Appointments\Services;

use App\Domains\Appointments\Enums\AppointmentStatus;
use App\Domains\Appointments\Enums\AppointmentType;
use App\Domains\Appointments\Models\Appointment;
use App\Domains\Appointments\Repositories\Contracts\AppointmentRepository;
use App\Domains\Audits\Data\AuditContext;
use App\Domains\Audits\Services\AuditService;
use App\Domains\Organizations\Enums\OrganizationStatus;
use App\Domains\Organizations\Models\Location;
use App\Domains\Patients\Enums\PatientStatus;
use App\Domains\Patients\Models\Patient;
use App\Domains\Professionals\Enums\ProfessionalStatus;
use App\Domains\Professionals\Enums\ScheduleExceptionType;
use App\Domains\Professionals\Models\ConsultingRoom;
use App\Domains\Professionals\Models\LocationHoliday;
use App\Domains\Professionals\Models\Professional;
use App\Domains\Professionals\Models\ProfessionalLeave;
use App\Domains\Professionals\Models\ProfessionalSchedule;
use App\Domains\Professionals\Models\ProfessionalScheduleException;
use App\Domains\Users\Enums\RoleCode;
use App\Domains\Users\Models\User;
use App\Domains\Users\Services\UserScopeService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AppointmentService
{
    public function __construct(
        private AppointmentRepository $appointments,
        private AuditService $audit,
        private UserScopeService $scope,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Appointment>
     */
    public function paginate(
        array $filters,
        int $perPage,
        ?int $professionalId,
        ?array $locationIds,
    ): LengthAwarePaginator {
        $normalizedFilters = Arr::only($filters, [
            'date',
            'status',
            'type',
            'location_id',
            'professional_id',
            'search',
        ]);

        if (isset($normalizedFilters['search'])) {
            $normalizedFilters['search'] = Str::squish(
                (string) $normalizedFilters['search'],
            );
        }

        if ($professionalId !== null) {
            $normalizedFilters['professional_id'] = $professionalId;
        }

        return $this->appointments->paginate(
            array_filter(
                $normalizedFilters,
                fn (mixed $value): bool => $value !== null && $value !== '',
            ),
            min(max($perPage, 1), 100),
            $professionalId,
            $locationIds,
        );
    }

    /** @return array{total: int, scheduled: int, waiting: int, completed: int, cancelled: int, absent: int, today: int} */
    public function summaries(?int $professionalId, ?array $locationIds): array
    {
        return $this->appointments->summaries($professionalId, $locationIds);
    }

    public function find(int $id): Appointment
    {
        return $this->appointments->findOrFail($id);
    }

    public function professionalScopeFor(User $user): ?int
    {
        if (! $user->hasRole(RoleCode::Professional)) {
            return null;
        }

        return $this->appointments->professionalIdForUser((int) $user->getKey()) ?? 0;
    }

    /** @return array<int, int>|null */
    public function locationScopeFor(User $user): ?array
    {
        return $this->scope->locationIdsFor($user);
    }

    /** @return Collection<int, Patient> */
    public function availablePatients(): Collection
    {
        return $this->appointments->availablePatients();
    }

    /** @return Collection<int, Professional> */
    public function activeProfessionals(?int $professionalId = null, ?array $locationIds = null): Collection
    {
        return $this->appointments->activeProfessionals($professionalId, $locationIds);
    }

    /** @return Collection<int, Location> */
    public function activeLocations(?array $locationIds = null): Collection
    {
        return $this->appointments->activeLocations($locationIds);
    }

    /** @return Collection<int, ConsultingRoom> */
    public function activeConsultingRooms(?array $locationIds = null): Collection
    {
        return ConsultingRoom::query()
            ->select(['id', 'location_id', 'name', 'code', 'sector'])
            ->where('is_active', true)
            ->when($locationIds !== null, fn ($query) => $query->whereIn('location_id', $locationIds))
            ->orderBy('location_id')
            ->orderBy('name')
            ->get();
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    public function agendaConstraints(?int $professionalId, ?array $locationIds): array
    {
        return [
            'holidays' => LocationHoliday::query()
                ->select(['location_id', 'date', 'name'])
                ->when($locationIds !== null, fn ($query) => $query->whereIn('location_id', $locationIds))
                ->whereDate('date', '>=', today())
                ->get()
                ->map(fn (LocationHoliday $holiday): array => [
                    'location_id' => $holiday->location_id,
                    'date' => $holiday->date->format('Y-m-d'),
                    'name' => $holiday->name,
                ])->all(),
            'leaves' => ProfessionalLeave::query()
                ->select(['professional_id', 'location_id', 'starts_on', 'ends_on', 'reason'])
                ->when($professionalId !== null, fn ($query) => $query->where('professional_id', $professionalId))
                ->when($locationIds !== null, fn ($query) => $query->where(
                    fn ($nested) => $nested->whereNull('location_id')->orWhereIn('location_id', $locationIds),
                ))
                ->whereDate('ends_on', '>=', today())
                ->get()
                ->map(fn (ProfessionalLeave $leave): array => [
                    'professional_id' => $leave->professional_id,
                    'location_id' => $leave->location_id,
                    'starts_on' => $leave->starts_on->format('Y-m-d'),
                    'ends_on' => $leave->ends_on->format('Y-m-d'),
                    'reason' => $leave->reason,
                ])->all(),
            'exceptions' => ProfessionalScheduleException::query()
                ->select(['professional_id', 'location_id', 'date', 'type', 'start_time', 'end_time', 'reason'])
                ->when($professionalId !== null, fn ($query) => $query->where('professional_id', $professionalId))
                ->when($locationIds !== null, fn ($query) => $query->whereIn('location_id', $locationIds))
                ->whereDate('date', '>=', today())
                ->get()
                ->map(fn (ProfessionalScheduleException $exception): array => [
                    'professional_id' => $exception->professional_id,
                    'location_id' => $exception->location_id,
                    'date' => $exception->date->format('Y-m-d'),
                    'type' => $exception->type->value,
                    'start_time' => $exception->start_time,
                    'end_time' => $exception->end_time,
                    'reason' => $exception->reason,
                ])->all(),
        ];
    }

    /** @param array<string, mixed> $data */
    public function create(
        array $data,
        User $actor,
        AuditContext $auditContext,
    ): Appointment {
        return DB::transaction(function () use ($data, $actor, $auditContext): Appointment {
            [$patient, $professional, $location] = $this->lockReferences($data);
            $this->ensureLocationAccess($actor, $location);
            [$startsAt, $endsAt, $durationMinutes] = $this->appointmentWindow(
                $data,
                $professional,
                $location,
            );
            $this->ensureCanSchedule(
                $patient,
                $professional,
                $location,
                $startsAt,
                $endsAt,
            );
            $room = $this->resolveConsultingRoom($data, $professional, $location, $startsAt, $endsAt);

            $appointment = $this->appointments->create(
                $this->appointmentAttributes(
                    $data,
                    $startsAt,
                    $endsAt,
                    $durationMinutes,
                    $room,
                    $professional,
                ),
            );

            $this->audit->record(
                event: 'appointment.created',
                auditable: $appointment,
                afterValues: $this->auditSnapshot($appointment),
                actor: $actor,
                context: $auditContext,
            );

            return $appointment;
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function update(
        Appointment $appointment,
        array $data,
        User $actor,
        AuditContext $auditContext,
    ): Appointment {
        return DB::transaction(function () use (
            $appointment,
            $data,
            $actor,
            $auditContext,
        ): Appointment {
            $lockedAppointment = $this->lockCurrentVersion(
                $appointment,
                (int) $data['lock_version'],
            );

            if (! in_array($lockedAppointment->status, [
                AppointmentStatus::Reserved,
                AppointmentStatus::Confirmed,
            ], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Sólo pueden reprogramarse turnos reservados o confirmados.',
                ]);
            }

            [$patient, $professional, $location] = $this->lockReferences($data);
            $this->ensureLocationAccess($actor, $location);
            [$startsAt, $endsAt, $durationMinutes] = $this->appointmentWindow(
                $data,
                $professional,
                $location,
            );
            $this->ensureCanSchedule(
                $patient,
                $professional,
                $location,
                $startsAt,
                $endsAt,
                (int) $lockedAppointment->getKey(),
            );
            $room = $this->resolveConsultingRoom(
                $data,
                $professional,
                $location,
                $startsAt,
                $endsAt,
                (int) $lockedAppointment->getKey(),
            );

            $beforeValues = $this->auditSnapshot($lockedAppointment);
            $updatedAppointment = $this->appointments->update(
                $lockedAppointment,
                [
                    ...$this->appointmentAttributes(
                        $data,
                        $startsAt,
                        $endsAt,
                        $durationMinutes,
                        $room,
                        $professional,
                    ),
                    'status' => $lockedAppointment->status,
                    'lock_version' => $lockedAppointment->lock_version + 1,
                ],
            );

            $this->audit->record(
                event: 'appointment.updated',
                auditable: $updatedAppointment,
                beforeValues: $beforeValues,
                afterValues: $this->auditSnapshot($updatedAppointment),
                actor: $actor,
                context: $auditContext,
            );

            return $updatedAppointment;
        }, 3);
    }

    public function changeStatus(
        Appointment $appointment,
        AppointmentStatus $status,
        int $lockVersion,
        User $actor,
        AuditContext $auditContext,
    ): Appointment {
        return DB::transaction(function () use (
            $appointment,
            $status,
            $lockVersion,
            $actor,
            $auditContext,
        ): Appointment {
            $lockedAppointment = $this->lockCurrentVersion($appointment, $lockVersion);

            if ($status === AppointmentStatus::Cancelled) {
                throw ValidationException::withMessages([
                    'status' => 'La cancelación debe realizarse mediante la acción específica.',
                ]);
            }

            if (! in_array(
                $status,
                $lockedAppointment->status->availableTransitions(),
                true,
            )) {
                throw ValidationException::withMessages([
                    'status' => 'El cambio de estado solicitado no está permitido.',
                ]);
            }

            $updatedAppointment = $this->appointments->update(
                $lockedAppointment,
                [
                    'status' => $status,
                    'lock_version' => $lockedAppointment->lock_version + 1,
                ],
            );

            $this->audit->record(
                event: 'appointment.status_changed',
                auditable: $updatedAppointment,
                actor: $actor,
                context: $auditContext,
            );

            return $updatedAppointment;
        }, 3);
    }

    public function cancel(
        Appointment $appointment,
        string $cancellationReason,
        int $lockVersion,
        User $actor,
        AuditContext $auditContext,
    ): Appointment {
        return DB::transaction(function () use (
            $appointment,
            $cancellationReason,
            $lockVersion,
            $actor,
            $auditContext,
        ): Appointment {
            $lockedAppointment = $this->lockCurrentVersion($appointment, $lockVersion);

            if (! $lockedAppointment->status->isCancellable()) {
                throw ValidationException::withMessages([
                    'status' => 'El turno ya no admite cancelación.',
                ]);
            }

            if ($lockedAppointment->starts_at->lt(now()->addHours(24))) {
                throw ValidationException::withMessages([
                    'cancellation_reason' => 'Los turnos sólo pueden cancelarse con al menos 24 horas de anticipación.',
                ]);
            }

            $beforeValues = $this->auditSnapshot($lockedAppointment);
            $cancelledAppointment = $this->appointments->update(
                $lockedAppointment,
                [
                    'status' => AppointmentStatus::Cancelled,
                    'cancellation_reason' => Str::squish($cancellationReason),
                    'cancelled_at' => now(),
                    'cancelled_by' => $actor->getKey(),
                    'lock_version' => $lockedAppointment->lock_version + 1,
                ],
            );

            $this->audit->record(
                event: 'appointment.cancelled',
                auditable: $cancelledAppointment,
                beforeValues: $beforeValues,
                afterValues: $this->auditSnapshot($cancelledAppointment),
                actor: $actor,
                context: $auditContext,
            );

            return $cancelledAppointment;
        }, 3);
    }

    /** @param array<string, mixed> $data */
    private function lockReferences(array $data): array
    {
        return [
            $this->appointments->lockPatient((int) $data['patient_id']),
            $this->appointments->lockProfessional((int) $data['professional_id']),
            $this->appointments->lockLocation((int) $data['location_id']),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: int}
     */
    private function appointmentWindow(
        array $data,
        Professional $professional,
        Location $location,
    ): array {
        $durationMinutes = (int) $professional->appointment_duration_minutes;

        if ($durationMinutes < 1 || $durationMinutes > 480) {
            throw ValidationException::withMessages([
                'professional_id' => 'El profesional no tiene una duración de turno válida.',
            ]);
        }

        $startsAt = CarbonImmutable::parse(
            (string) $data['starts_at'],
            $location->timezone ?: config('app.timezone'),
        );
        $endsAt = $startsAt->addMinutes($durationMinutes);

        if (! $startsAt->isFuture()) {
            throw ValidationException::withMessages([
                'starts_at' => 'El turno debe programarse para una fecha y hora futuras.',
            ]);
        }

        return [$startsAt, $endsAt, $durationMinutes];
    }

    private function ensureCanSchedule(
        Patient $patient,
        Professional $professional,
        Location $location,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        ?int $exceptAppointmentId = null,
    ): void {
        if (! in_array($patient->status, [
            PatientStatus::Active,
            PatientStatus::Inpatient,
        ], true)) {
            throw ValidationException::withMessages([
                'patient_id' => 'El paciente no está habilitado para recibir turnos.',
            ]);
        }

        if ($professional->status !== ProfessionalStatus::Active) {
            throw ValidationException::withMessages([
                'professional_id' => 'El profesional seleccionado no está activo.',
            ]);
        }

        if ($location->status !== OrganizationStatus::Active) {
            throw ValidationException::withMessages([
                'location_id' => 'La sede seleccionada no está activa.',
            ]);
        }

        $this->ensureWithinLocationHours($location, $startsAt, $endsAt);
        $this->ensureWithinProfessionalSchedule(
            $professional,
            $location,
            $startsAt,
            $endsAt,
        );

        if ($this->appointments->hasProfessionalConflict(
            (int) $professional->getKey(),
            $startsAt,
            $endsAt,
            $exceptAppointmentId,
        )) {
            throw ValidationException::withMessages([
                'starts_at' => 'El profesional ya tiene un turno que se superpone en ese horario.',
            ]);
        }

        if ($this->appointments->hasPatientConflict(
            (int) $patient->getKey(),
            $startsAt,
            $endsAt,
            $exceptAppointmentId,
        )) {
            throw ValidationException::withMessages([
                'starts_at' => 'El paciente ya tiene un turno que se superpone en ese horario.',
            ]);
        }

    }

    private function ensureWithinLocationHours(
        Location $location,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
    ): void {
        $startTime = $startsAt->format('H:i:s');
        $endTime = $endsAt->format('H:i:s');
        $openingTime = $this->normalizedTime($location->opening_time);
        $closingTime = $this->normalizedTime($location->closing_time);

        if ($startTime < $openingTime || $endTime > $closingTime) {
            throw ValidationException::withMessages([
                'starts_at' => 'El turno debe quedar dentro del horario operativo de la sede.',
            ]);
        }
    }

    private function ensureWithinProfessionalSchedule(
        Professional $professional,
        Location $location,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
    ): void {
        if (LocationHoliday::query()
            ->where('location_id', $location->getKey())
            ->whereDate('date', $startsAt->toDateString())
            ->exists()) {
            throw ValidationException::withMessages([
                'starts_at' => 'La sede no atiende en la fecha seleccionada por feriado.',
            ]);
        }

        if (ProfessionalLeave::query()
            ->where('professional_id', $professional->getKey())
            ->where(fn ($query) => $query
                ->whereNull('location_id')
                ->orWhere('location_id', $location->getKey()))
            ->whereDate('starts_on', '<=', $startsAt->toDateString())
            ->whereDate('ends_on', '>=', $startsAt->toDateString())
            ->exists()) {
            throw ValidationException::withMessages([
                'starts_at' => 'El profesional se encuentra de licencia en la fecha seleccionada.',
            ]);
        }

        $startTime = $startsAt->format('H:i:s');
        $endTime = $endsAt->format('H:i:s');
        $exception = ProfessionalScheduleException::query()
            ->where('professional_id', $professional->getKey())
            ->where('location_id', $location->getKey())
            ->whereDate('date', $startsAt->toDateString())
            ->first();

        if ($exception !== null) {
            $available = $exception->type === ScheduleExceptionType::Available
                && $startTime >= $this->normalizedTime((string) $exception->start_time)
                && $endTime <= $this->normalizedTime((string) $exception->end_time);

            if (! $available) {
                throw ValidationException::withMessages([
                    'starts_at' => 'El horario está bloqueado por una excepción de agenda.',
                ]);
            }

            return;
        }

        $normalizedLocationName = Str::lower(Str::squish($location->name));
        $matchingSchedule = $professional->schedules()
            ->with('breaks')
            ->where('weekday', $startsAt->dayOfWeekIso)
            ->get(['id', 'location_id', 'location_name', 'start_time', 'end_time'])
            ->first(
                fn (ProfessionalSchedule $schedule): bool => (
                    $schedule->location_id !== null
                        ? $schedule->location_id === (int) $location->getKey()
                        : Str::lower(
                            Str::squish($schedule->location_name),
                        ) === $normalizedLocationName
                )
                    && $startTime >= $this->normalizedTime($schedule->start_time)
                    && $endTime <= $this->normalizedTime($schedule->end_time),
            );

        if ($matchingSchedule === null) {
            throw ValidationException::withMessages([
                'starts_at' => 'El horario no pertenece a la agenda del profesional en la sede seleccionada.',
            ]);
        }

        if ($matchingSchedule->breaks->contains(
            fn ($break): bool => $startTime < $this->normalizedTime($break->end_time)
                && $endTime > $this->normalizedTime($break->start_time),
        )) {
            throw ValidationException::withMessages([
                'starts_at' => 'El horario se superpone con un descanso del profesional.',
            ]);
        }
    }

    private function lockCurrentVersion(
        Appointment $appointment,
        int $lockVersion,
    ): Appointment {
        $lockedAppointment = $this->appointments->lockForUpdate($appointment);

        if ($lockedAppointment->lock_version !== $lockVersion) {
            throw ValidationException::withMessages([
                'lock_version' => 'El turno fue modificado por otro usuario. Recargá la página antes de continuar.',
            ]);
        }

        return $lockedAppointment;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function appointmentAttributes(
        array $data,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        int $durationMinutes,
        ?ConsultingRoom $room,
        Professional $professional,
    ): array {
        $type = AppointmentType::from((string) $data['type']);

        return [
            'patient_id' => (int) $data['patient_id'],
            'professional_id' => (int) $data['professional_id'],
            'location_id' => (int) $data['location_id'],
            'consulting_room_id' => $room?->getKey(),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'duration_minutes' => $durationMinutes,
            'type' => $type,
            'status' => AppointmentStatus::Reserved,
            'office' => $room?->name
                ?? ($type === AppointmentType::Virtual
                    ? null
                    : ($this->nullableString($data['office'] ?? null)
                        ?? $this->nullableString($professional->fixed_office))),
            'reason' => $this->nullableString($data['reason'] ?? null),
            'is_overbook' => false,
            'lock_version' => 1,
        ];
    }

    /** @param array<string, mixed> $data */
    private function resolveConsultingRoom(
        array $data,
        Professional $professional,
        Location $location,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        ?int $exceptAppointmentId = null,
    ): ?ConsultingRoom {
        if (AppointmentType::from((string) $data['type']) === AppointmentType::Virtual) {
            return null;
        }

        $requestedRoomId = isset($data['consulting_room_id'])
            ? (int) $data['consulting_room_id']
            : null;
        $fixedRoomId = $professional->schedules()
            ->where('location_id', $location->getKey())
            ->where('weekday', $startsAt->dayOfWeekIso)
            ->where('start_time', '<=', $startsAt->format('H:i:s'))
            ->where('end_time', '>=', $endsAt->format('H:i:s'))
            ->value('consulting_room_id');
        $preferredRoomId = $requestedRoomId;

        if ($preferredRoomId === null
            && $fixedRoomId !== null
            && ConsultingRoom::query()
                ->whereKey($fixedRoomId)
                ->where('location_id', $location->getKey())
                ->where('is_active', true)
                ->exists()) {
            $preferredRoomId = (int) $fixedRoomId;
        }

        $rooms = ConsultingRoom::query()
            ->where('location_id', $location->getKey())
            ->where('is_active', true)
            ->when($preferredRoomId !== null, fn ($query) => $query->whereKey($preferredRoomId))
            ->orderBy('name')
            ->lockForUpdate()
            ->get();

        if ($preferredRoomId !== null && $rooms->isEmpty()) {
            throw ValidationException::withMessages([
                'consulting_room_id' => 'El consultorio seleccionado no está activo o no pertenece a la sede.',
            ]);
        }

        $availableRoom = $rooms->first(fn (ConsultingRoom $room): bool => ! Appointment::query()
            ->where('consulting_room_id', $room->getKey())
            ->whereIn('status', AppointmentStatus::blockingValues())
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->when($exceptAppointmentId !== null, fn ($query) => $query->whereKeyNot($exceptAppointmentId))
            ->exists());

        if ($availableRoom === null && $rooms->isEmpty() && $preferredRoomId === null) {
            $legacyOffice = $this->nullableString($data['office'] ?? null)
                ?? $this->nullableString($professional->fixed_office);

            if ($legacyOffice !== null && $this->appointments->hasOfficeConflict(
                (int) $location->getKey(),
                $legacyOffice,
                $startsAt,
                $endsAt,
                $exceptAppointmentId,
            )) {
                throw ValidationException::withMessages([
                    'office' => 'El consultorio ya está ocupado durante ese horario.',
                ]);
            }

            return null;
        }

        if ($availableRoom === null) {
            throw ValidationException::withMessages([
                'consulting_room_id' => $preferredRoomId === null
                    ? 'No hay consultorios disponibles durante ese horario.'
                    : 'El consultorio seleccionado ya está ocupado durante ese horario.',
            ]);
        }

        return $availableRoom;
    }

    /** @return array<string, mixed> */
    private function auditSnapshot(Appointment $appointment): array
    {
        return [
            'patient_id' => $appointment->patient_id,
            'professional_id' => $appointment->professional_id,
            'location_id' => $appointment->location_id,
            'consulting_room_id' => $appointment->consulting_room_id,
            'starts_at' => $appointment->starts_at->toISOString(),
            'ends_at' => $appointment->ends_at->toISOString(),
            'duration_minutes' => $appointment->duration_minutes,
            'type' => $appointment->type->value,
            'status' => $appointment->status->value,
            'office' => $appointment->office,
            'is_overbook' => $appointment->is_overbook,
            'cancelled_at' => $appointment->cancelled_at?->toISOString(),
            'cancelled_by' => $appointment->cancelled_by,
            'has_cancellation_reason' => filled($appointment->cancellation_reason),
            'lock_version' => $appointment->lock_version,
        ];
    }

    private function normalizedTime(string $time): string
    {
        return Str::length($time) === 5 ? "$time:00" : $time;
    }

    private function nullableString(mixed $value): ?string
    {
        return filled($value) ? Str::squish((string) $value) : null;
    }

    private function ensureLocationAccess(User $actor, Location $location): void
    {
        if (! $this->scope->canAccessLocation($actor, (int) $location->getKey())) {
            throw ValidationException::withMessages([
                'location_id' => 'La sede seleccionada está fuera de tu alcance.',
            ]);
        }
    }
}
