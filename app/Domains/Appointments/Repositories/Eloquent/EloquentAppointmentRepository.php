<?php

namespace App\Domains\Appointments\Repositories\Eloquent;

use App\Domains\Appointments\Enums\AppointmentStatus;
use App\Domains\Appointments\Models\Appointment;
use App\Domains\Appointments\Repositories\Contracts\AppointmentRepository;
use App\Domains\Organizations\Enums\OrganizationStatus;
use App\Domains\Organizations\Models\Location;
use App\Domains\Patients\Enums\PatientStatus;
use App\Domains\Patients\Models\Patient;
use App\Domains\Professionals\Enums\ProfessionalStatus;
use App\Domains\Professionals\Models\Professional;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EloquentAppointmentRepository implements AppointmentRepository
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Appointment>
     */
    public function paginate(array $filters, int $perPage, ?int $professionalId, ?array $locationIds, ?int $patientId = null): LengthAwarePaginator
    {
        return $this->scopedQuery($professionalId, $locationIds, $patientId)
            ->select([
                'id',
                'patient_id',
                'professional_id',
                'location_id',
                'consulting_room_id',
                'starts_at',
                'ends_at',
                'duration_minutes',
                'type',
                'status',
                'office',
                'reason',
                'is_overbook',
                'cancellation_reason',
                'cancelled_at',
                'cancelled_by',
                'lock_version',
            ])
            ->with([
                'patient:id,document_number,first_name,last_name',
                'professional:id,user_id,specialty',
                'professional.user:id,name',
                'location:id,name,code',
                'consultingRoom:id,name,code',
                'cancelledBy:id,name',
            ])
            ->when(
                $patientId !== null,
                fn (Builder $query) => $query->where('starts_at', '>=', today()),
            )
            ->when(
                $filters['date'] ?? null,
                fn (Builder $query, string $date) => $query->whereDate('starts_at', $date),
            )
            ->when(
                $filters['status'] ?? null,
                fn (Builder $query, string $status) => $query->where('status', $status),
            )
            ->when(
                $filters['type'] ?? null,
                fn (Builder $query, string $type) => $query->where('type', $type),
            )
            ->when(
                $filters['location_id'] ?? null,
                fn (Builder $query, int $locationId) => $query->where('location_id', $locationId),
            )
            ->when(
                $filters['professional_id'] ?? null,
                fn (Builder $query, int $filteredProfessionalId) => $query
                    ->where('professional_id', $filteredProfessionalId),
            )
            ->when(
                $filters['search'] ?? null,
                fn (Builder $query, string $search) => $this->applySearch($query, $search),
            )
            ->orderBy('starts_at')
            ->orderBy('id')
            ->paginate($perPage);
    }

    /** @return array{total: int, scheduled: int, waiting: int, completed: int, cancelled: int, absent: int, today: int} */
    public function summaries(?int $professionalId, ?array $locationIds, ?int $patientId = null): array
    {
        $summary = $this->scopedQuery($professionalId, $locationIds, $patientId)
            ->toBase()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw(
                'COUNT(CASE WHEN status IN (?, ?) AND starts_at >= ? THEN 1 END) as scheduled',
                [
                    AppointmentStatus::Reserved->value,
                    AppointmentStatus::Confirmed->value,
                    now(),
                ],
            )
            ->selectRaw(
                'COUNT(CASE WHEN status IN (?, ?) THEN 1 END) as waiting',
                [AppointmentStatus::Waiting->value, AppointmentStatus::Present->value],
            )
            ->selectRaw(
                'COUNT(CASE WHEN status = ? THEN 1 END) as completed',
                [AppointmentStatus::Attended->value],
            )
            ->selectRaw(
                'COUNT(CASE WHEN status = ? THEN 1 END) as cancelled',
                [AppointmentStatus::Cancelled->value],
            )
            ->selectRaw(
                'COUNT(CASE WHEN status = ? THEN 1 END) as absent',
                [AppointmentStatus::Absent->value],
            )
            ->first();

        return [
            'total' => (int) ($summary->total ?? 0),
            'scheduled' => (int) ($summary->scheduled ?? 0),
            'waiting' => (int) ($summary->waiting ?? 0),
            'completed' => (int) ($summary->completed ?? 0),
            'cancelled' => (int) ($summary->cancelled ?? 0),
            'absent' => (int) ($summary->absent ?? 0),
            'today' => $this->scopedQuery($professionalId, $locationIds, $patientId)
                ->whereDate('starts_at', today())
                ->count(),
        ];
    }

    public function findOrFail(int $id): Appointment
    {
        return Appointment::query()
            ->with([
                'patient:id,document_number,first_name,last_name',
                'professional:id,user_id,specialty,appointment_duration_minutes',
                'professional.user:id,name',
                'location:id,name,code',
                'consultingRoom:id,name,code',
                'cancelledBy:id,name',
            ])
            ->findOrFail($id);
    }

    public function lockForUpdate(Appointment $appointment): Appointment
    {
        return Appointment::query()
            ->lockForUpdate()
            ->findOrFail($appointment->getKey());
    }

    public function lockPatient(int $id): Patient
    {
        return Patient::query()->lockForUpdate()->findOrFail($id);
    }

    public function lockProfessional(int $id): Professional
    {
        return Professional::query()->lockForUpdate()->findOrFail($id);
    }

    public function lockLocation(int $id): Location
    {
        return Location::query()->lockForUpdate()->findOrFail($id);
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Appointment
    {
        return $this->findOrFail(
            (int) Appointment::query()->create($attributes)->getKey(),
        );
    }

    /** @param array<string, mixed> $attributes */
    public function update(Appointment $appointment, array $attributes): Appointment
    {
        $appointment->update($attributes);

        return $this->findOrFail((int) $appointment->getKey());
    }

    public function hasProfessionalConflict(
        int $professionalId,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        ?int $exceptAppointmentId = null,
    ): bool {
        return $this->conflictQuery($startsAt, $endsAt, $exceptAppointmentId)
            ->where('professional_id', $professionalId)
            ->select('id')
            ->lockForUpdate()
            ->first() !== null;
    }

    public function hasPatientConflict(
        int $patientId,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        ?int $exceptAppointmentId = null,
    ): bool {
        return $this->conflictQuery($startsAt, $endsAt, $exceptAppointmentId)
            ->where('patient_id', $patientId)
            ->select('id')
            ->lockForUpdate()
            ->first() !== null;
    }

    public function hasOfficeConflict(
        int $locationId,
        string $office,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        ?int $exceptAppointmentId = null,
    ): bool {
        return $this->conflictQuery($startsAt, $endsAt, $exceptAppointmentId)
            ->where('location_id', $locationId)
            ->where('office', $office)
            ->select('id')
            ->lockForUpdate()
            ->first() !== null;
    }

    /** @return Collection<int, Patient> */
    public function availablePatients(): Collection
    {
        return Patient::query()
            ->select('id', 'document_number', 'first_name', 'last_name')
            ->whereIn('status', [
                PatientStatus::Active->value,
                PatientStatus::Inpatient->value,
            ])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /** @return Collection<int, Professional> */
    public function activeProfessionals(?int $onlyProfessionalId = null, ?array $locationIds = null): Collection
    {
        return Professional::query()
            ->select('id', 'user_id', 'specialty', 'appointment_duration_minutes')
            ->with([
                'user:id,name',
                'schedules' => fn ($query) => $query
                    ->select(
                        'id',
                        'professional_id',
                        'location_id',
                        'location_name',
                        'weekday',
                        'start_time',
                        'end_time',
                    )
                    ->with('breaks:id,professional_schedule_id,start_time,end_time,label')
                    ->orderBy('weekday')
                    ->orderBy('start_time'),
            ])
            ->where('status', ProfessionalStatus::Active->value)
            ->when(
                $onlyProfessionalId !== null,
                fn (Builder $query) => $query->whereKey($onlyProfessionalId),
            )
            ->when(
                $locationIds !== null,
                fn (Builder $query) => $query->whereHas(
                    'schedules',
                    fn (Builder $scheduleQuery) => $scheduleQuery
                        ->whereIn('location_id', $locationIds),
                ),
            )
            ->whereHas('user')
            ->orderBy('id')
            ->get()
            ->sortBy(fn (Professional $professional): string => $professional->user->name)
            ->values();
    }

    /** @return Collection<int, Location> */
    public function activeLocations(?array $locationIds = null): Collection
    {
        return Location::query()
            ->select('id', 'name', 'code')
            ->where('status', OrganizationStatus::Active->value)
            ->when(
                $locationIds !== null,
                fn (Builder $query) => $query->whereKey($locationIds),
            )
            ->orderBy('name')
            ->get();
    }

    public function professionalIdForUser(int $userId): ?int
    {
        $professionalId = Professional::query()
            ->where('user_id', $userId)
            ->value('id');

        return $professionalId === null ? null : (int) $professionalId;
    }

    /** @param array<int, int>|null $locationIds */
    private function scopedQuery(?int $professionalId, ?array $locationIds, ?int $patientId = null): Builder
    {
        return Appointment::query()
            ->when(
                $professionalId !== null,
                fn (Builder $query) => $query->where('professional_id', $professionalId),
            )
            ->when(
                $locationIds !== null,
                fn (Builder $query) => $query->whereIn('location_id', $locationIds),
            )
            ->when($patientId !== null, fn (Builder $query) => $query->where('patient_id', $patientId));
    }

    private function conflictQuery(
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        ?int $exceptAppointmentId,
    ): Builder {
        return Appointment::query()
            ->whereIn('status', AppointmentStatus::blockingValues())
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->when(
                $exceptAppointmentId !== null,
                fn (Builder $query) => $query->whereKeyNot($exceptAppointmentId),
            );
    }

    private function applySearch(Builder $query, string $search): Builder
    {
        return $query->where(
            fn (Builder $nestedQuery) => $nestedQuery
                ->where('office', 'like', "%$search%")
                ->orWhereHas(
                    'patient',
                    fn (Builder $patientQuery) => $patientQuery
                        ->where('document_number', 'like', "%$search%")
                        ->orWhere('first_name', 'like', "%$search%")
                        ->orWhere('last_name', 'like', "%$search%"),
                )
                ->orWhereHas(
                    'professional.user',
                    fn (Builder $userQuery) => $userQuery
                        ->where('name', 'like', "%$search%"),
                )
                ->orWhereHas(
                    'location',
                    fn (Builder $locationQuery) => $locationQuery
                        ->where('name', 'like', "%$search%")
                        ->orWhere('code', 'like', "%$search%"),
                ),
        );
    }
}
