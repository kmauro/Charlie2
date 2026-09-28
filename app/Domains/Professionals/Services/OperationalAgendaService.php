<?php

namespace App\Domains\Professionals\Services;

use App\Domains\Audits\Data\AuditContext;
use App\Domains\Audits\Services\AuditService;
use App\Domains\Organizations\Enums\OrganizationStatus;
use App\Domains\Organizations\Models\Location;
use App\Domains\Professionals\Models\ConsultingRoom;
use App\Domains\Professionals\Models\LocationHoliday;
use App\Domains\Professionals\Models\Professional;
use App\Domains\Professionals\Models\ProfessionalLeave;
use App\Domains\Professionals\Models\ProfessionalSchedule;
use App\Domains\Professionals\Models\ProfessionalScheduleBreak;
use App\Domains\Professionals\Models\ProfessionalScheduleException;
use App\Domains\Users\Enums\RoleCode;
use App\Domains\Users\Models\User;
use App\Domains\Users\Services\UserScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OperationalAgendaService
{
    public function __construct(
        private UserScopeService $scope,
        private AuditService $audit,
    ) {}

    /** @return array<string, mixed> */
    public function pageData(User $actor): array
    {
        $locationIds = $this->scope->locationIdsFor($actor);
        $locations = Location::query()
            ->select(['id', 'name', 'code'])
            ->where('status', OrganizationStatus::Active->value)
            ->whereNull('archived_at')
            ->when($locationIds !== null, fn (Builder $query) => $query->whereKey($locationIds))
            ->orderBy('name')
            ->get();

        $visibleLocationIds = $locations->modelKeys();
        $onlyProfessionalId = $actor->hasRole(RoleCode::Professional)
            ? Professional::query()->where('user_id', $actor->getKey())->value('id')
            : null;
        $professionals = Professional::query()
            ->select(['id', 'user_id', 'specialty'])
            ->with('user:id,name')
            ->whereHas('schedules', fn (Builder $query) => $query->whereIn('location_id', $visibleLocationIds))
            ->when($onlyProfessionalId !== null, fn (Builder $query) => $query->whereKey($onlyProfessionalId))
            ->orderBy('id')
            ->get()
            ->sortBy(fn (Professional $professional): string => $professional->user->name)
            ->values();

        return [
            'locations' => $locations->map(fn (Location $location): array => [
                'value' => (int) $location->getKey(),
                'label' => $location->name,
                'code' => $location->code,
            ])->all(),
            'professionals' => $professionals->map(fn (Professional $professional): array => [
                'value' => (int) $professional->getKey(),
                'label' => $professional->user->name,
                'specialty' => $professional->specialty,
            ])->all(),
            'rooms' => ConsultingRoom::query()
                ->with('location:id,name')
                ->whereIn('location_id', $visibleLocationIds)
                ->orderBy('location_id')
                ->orderBy('name')
                ->get()
                ->map(fn (ConsultingRoom $room): array => $this->roomData($room))
                ->all(),
            'holidays' => LocationHoliday::query()
                ->with('location:id,name')
                ->whereIn('location_id', $visibleLocationIds)
                ->orderBy('date')
                ->get()
                ->map(fn (LocationHoliday $holiday): array => [
                    'id' => (int) $holiday->getKey(),
                    'location_id' => $holiday->location_id,
                    'location_name' => $holiday->location->name,
                    'date' => $holiday->date->format('Y-m-d'),
                    'name' => $holiday->name,
                ])->all(),
            'leaves' => ProfessionalLeave::query()
                ->with(['professional.user:id,name', 'location:id,name'])
                ->whereHas('professional.schedules', fn (Builder $query) => $query->whereIn('location_id', $visibleLocationIds))
                ->when($onlyProfessionalId !== null, fn (Builder $query) => $query->where('professional_id', $onlyProfessionalId))
                ->when($locationIds !== null, fn (Builder $query) => $query->where(
                    fn (Builder $nested) => $nested->whereNull('location_id')->orWhereIn('location_id', $visibleLocationIds),
                ))
                ->orderBy('starts_on')
                ->get()
                ->map(fn (ProfessionalLeave $leave): array => [
                    'id' => (int) $leave->getKey(),
                    'professional_id' => $leave->professional_id,
                    'professional_name' => $leave->professional->user->name,
                    'location_id' => $leave->location_id,
                    'location_name' => $leave->location?->name ?? 'Todas las sedes',
                    'starts_on' => $leave->starts_on->format('Y-m-d'),
                    'ends_on' => $leave->ends_on->format('Y-m-d'),
                    'reason' => $leave->reason,
                ])->all(),
            'exceptions' => ProfessionalScheduleException::query()
                ->with(['professional.user:id,name', 'location:id,name'])
                ->whereIn('location_id', $visibleLocationIds)
                ->when($onlyProfessionalId !== null, fn (Builder $query) => $query->where('professional_id', $onlyProfessionalId))
                ->orderBy('date')
                ->get()
                ->map(fn (ProfessionalScheduleException $exception): array => [
                    'id' => (int) $exception->getKey(),
                    'professional_id' => $exception->professional_id,
                    'professional_name' => $exception->professional->user->name,
                    'location_id' => $exception->location_id,
                    'location_name' => $exception->location->name,
                    'date' => $exception->date->format('Y-m-d'),
                    'type' => $exception->type->value,
                    'type_label' => $exception->type->label(),
                    'start_time' => $exception->start_time,
                    'end_time' => $exception->end_time,
                    'reason' => $exception->reason,
                ])->all(),
            'schedules' => ProfessionalSchedule::query()
                ->with(['professional.user:id,name', 'location:id,name', 'consultingRoom:id,name,code', 'breaks'])
                ->whereIn('location_id', $visibleLocationIds)
                ->when($onlyProfessionalId !== null, fn (Builder $query) => $query->where('professional_id', $onlyProfessionalId))
                ->orderBy('weekday')
                ->orderBy('start_time')
                ->get()
                ->map(fn (ProfessionalSchedule $schedule): array => [
                    'id' => (int) $schedule->getKey(),
                    'professional_name' => $schedule->professional->user->name,
                    'location_id' => $schedule->location_id,
                    'location_name' => $schedule->location?->name ?? $schedule->location_name,
                    'weekday' => $schedule->weekday,
                    'weekday_label' => $this->weekdayLabel($schedule->weekday),
                    'start_time' => $schedule->start_time,
                    'end_time' => $schedule->end_time,
                    'consulting_room_id' => $schedule->consulting_room_id,
                    'consulting_room_name' => $schedule->consultingRoom?->name,
                    'breaks' => $schedule->breaks->map(fn (ProfessionalScheduleBreak $break): array => [
                        'id' => (int) $break->getKey(),
                        'start_time' => $break->start_time,
                        'end_time' => $break->end_time,
                        'label' => $break->label,
                    ])->all(),
                ])->all(),
        ];
    }

    /** @param array<string, mixed> $data */
    public function createRoom(array $data, User $actor, AuditContext $context): ConsultingRoom
    {
        $this->ensureLocationAccess($actor, (int) $data['location_id']);

        return DB::transaction(function () use ($data, $actor, $context): ConsultingRoom {
            $room = ConsultingRoom::query()->create([
                ...$data,
                'name' => Str::squish((string) $data['name']),
                'code' => Str::upper(Str::squish((string) $data['code'])),
                'equipment' => array_values(array_unique(array_map(
                    fn (mixed $item): string => Str::squish((string) $item),
                    $data['equipment'] ?? [],
                ))),
                'is_active' => true,
            ]);
            $this->record('agenda.room.created', $room, $actor, $context);

            return $room;
        }, 3);
    }

    public function deactivateRoom(ConsultingRoom $room, User $actor, AuditContext $context): void
    {
        $this->ensureLocationAccess($actor, (int) $room->location_id);
        $room->update(['is_active' => false]);
        $this->record('agenda.room.deactivated', $room, $actor, $context);
    }

    /** @param array<string, mixed> $data */
    public function createHoliday(array $data, User $actor, AuditContext $context): LocationHoliday
    {
        $this->ensureLocationAccess($actor, (int) $data['location_id']);
        $holiday = LocationHoliday::query()->create($data);
        $this->record('agenda.holiday.created', $holiday, $actor, $context);

        return $holiday;
    }

    /** @param array<string, mixed> $data */
    public function createLeave(array $data, User $actor, AuditContext $context): ProfessionalLeave
    {
        $locationId = isset($data['location_id']) ? (int) $data['location_id'] : null;

        if ($locationId === null && ! $actor->hasRole(RoleCode::SuperAdmin)) {
            throw ValidationException::withMessages(['location_id' => 'Debés seleccionar una sede dentro de tu alcance.']);
        }

        $this->ensureProfessionalAccess($actor, (int) $data['professional_id'], $locationId);
        $overlapQuery = ProfessionalLeave::query()
            ->where('professional_id', $data['professional_id'])
            ->where('starts_on', '<=', $data['ends_on'])
            ->where('ends_on', '>=', $data['starts_on']);

        if ($locationId !== null) {
            $overlapQuery->where(fn (Builder $query) => $query
                ->whereNull('location_id')
                ->orWhere('location_id', $locationId));
        }

        $overlaps = $overlapQuery->exists();

        if ($overlaps) {
            throw ValidationException::withMessages(['starts_on' => 'La licencia se superpone con otra existente.']);
        }

        $leave = ProfessionalLeave::query()->create($data);
        $this->record('agenda.leave.created', $leave, $actor, $context);

        return $leave;
    }

    /** @param array<string, mixed> $data */
    public function createException(array $data, User $actor, AuditContext $context): ProfessionalScheduleException
    {
        $this->ensureProfessionalAccess($actor, (int) $data['professional_id'], (int) $data['location_id']);
        $exception = ProfessionalScheduleException::query()->create($data);
        $this->record('agenda.exception.created', $exception, $actor, $context);

        return $exception;
    }

    /** @param array<string, mixed> $data */
    public function createBreak(ProfessionalSchedule $schedule, array $data, User $actor, AuditContext $context): ProfessionalScheduleBreak
    {
        $this->ensureLocationAccess($actor, (int) $schedule->location_id);
        $start = (string) $data['start_time'];
        $end = (string) $data['end_time'];

        if ($start < substr($schedule->start_time, 0, 5) || $end > substr($schedule->end_time, 0, 5)) {
            throw ValidationException::withMessages(['start_time' => 'El descanso debe quedar dentro de la franja profesional.']);
        }

        if ($schedule->breaks()->where('start_time', '<', $end)->where('end_time', '>', $start)->exists()) {
            throw ValidationException::withMessages(['start_time' => 'El descanso se superpone con otro existente.']);
        }

        $break = $schedule->breaks()->create($data);
        $this->record('agenda.break.created', $break, $actor, $context);

        return $break;
    }

    public function assignRoom(ProfessionalSchedule $schedule, ?int $roomId, User $actor, AuditContext $context): void
    {
        $this->ensureLocationAccess($actor, (int) $schedule->location_id);

        if ($roomId !== null && ! ConsultingRoom::query()
            ->whereKey($roomId)
            ->where('location_id', $schedule->location_id)
            ->where('is_active', true)
            ->exists()) {
            throw ValidationException::withMessages(['consulting_room_id' => 'El consultorio debe estar activo y pertenecer a la misma sede.']);
        }

        $schedule->update(['consulting_room_id' => $roomId]);
        $this->record('agenda.schedule_room.updated', $schedule, $actor, $context);
    }

    public function delete(Model $record, string $event, User $actor, AuditContext $context, ?int $locationId): void
    {
        if ($record instanceof ProfessionalLeave
            && $locationId === null
            && ! $actor->hasRole(RoleCode::SuperAdmin)) {
            throw ValidationException::withMessages([
                'location_id' => 'Sólo el superadministrador puede eliminar licencias de todas las sedes.',
            ]);
        }

        if ($locationId !== null) {
            $this->ensureLocationAccess($actor, $locationId);
        }

        $this->record($event, $record, $actor, $context);
        $record->delete();
    }

    private function ensureProfessionalAccess(User $actor, int $professionalId, ?int $locationId): void
    {
        if ($locationId !== null) {
            $this->ensureLocationAccess($actor, $locationId);
        }

        $query = ProfessionalSchedule::query()->where('professional_id', $professionalId);

        if ($locationId !== null) {
            $query->where('location_id', $locationId);
        } else {
            $locationIds = $this->scope->locationIdsFor($actor);
            $query->when($locationIds !== null, fn (Builder $builder) => $builder->whereIn('location_id', $locationIds));
        }

        if (! $query->exists()) {
            throw ValidationException::withMessages(['professional_id' => 'El profesional no atiende en una sede dentro de tu alcance.']);
        }
    }

    private function ensureLocationAccess(User $actor, int $locationId): void
    {
        if (! $this->scope->canAccessLocation($actor, $locationId)) {
            throw ValidationException::withMessages(['location_id' => 'La sede seleccionada está fuera de tu alcance.']);
        }
    }

    private function record(string $event, Model $record, User $actor, AuditContext $context): void
    {
        $this->audit->record(event: $event, auditable: $record, actor: $actor, context: $context);
    }

    /** @return array<string, mixed> */
    private function roomData(ConsultingRoom $room): array
    {
        return [
            'id' => (int) $room->getKey(),
            'location_id' => $room->location_id,
            'location_name' => $room->location->name,
            'name' => $room->name,
            'code' => $room->code,
            'sector' => $room->sector,
            'floor' => $room->floor,
            'equipment' => $room->equipment ?? [],
            'operational_restrictions' => $room->operational_restrictions,
            'is_active' => $room->is_active,
        ];
    }

    private function weekdayLabel(int $weekday): string
    {
        return match ($weekday) {
            1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves',
            5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo', default => "Día $weekday",
        };
    }
}
