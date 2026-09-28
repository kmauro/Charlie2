<?php

namespace App\Domains\Appointments\Repositories\Contracts;

use App\Domains\Appointments\Models\Appointment;
use App\Domains\Organizations\Models\Location;
use App\Domains\Patients\Models\Patient;
use App\Domains\Professionals\Models\Professional;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface AppointmentRepository
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Appointment>
     */
    /** @param array<int, int>|null $locationIds */
    public function paginate(array $filters, int $perPage, ?int $professionalId, ?array $locationIds, ?int $patientId = null): LengthAwarePaginator;

    /** @return array{total: int, scheduled: int, waiting: int, completed: int, cancelled: int, absent: int, today: int} */
    /** @param array<int, int>|null $locationIds */
    public function summaries(?int $professionalId, ?array $locationIds, ?int $patientId = null): array;

    public function findOrFail(int $id): Appointment;

    public function lockForUpdate(Appointment $appointment): Appointment;

    public function lockPatient(int $id): Patient;

    public function lockProfessional(int $id): Professional;

    public function lockLocation(int $id): Location;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Appointment;

    /** @param array<string, mixed> $attributes */
    public function update(Appointment $appointment, array $attributes): Appointment;

    public function hasProfessionalConflict(
        int $professionalId,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        ?int $exceptAppointmentId = null,
    ): bool;

    public function hasPatientConflict(
        int $patientId,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        ?int $exceptAppointmentId = null,
    ): bool;

    public function hasOfficeConflict(
        int $locationId,
        string $office,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        ?int $exceptAppointmentId = null,
    ): bool;

    /** @return Collection<int, Patient> */
    public function availablePatients(): Collection;

    /** @return Collection<int, Professional> */
    /** @param array<int, int>|null $locationIds */
    public function activeProfessionals(?int $onlyProfessionalId = null, ?array $locationIds = null): Collection;

    /** @return Collection<int, Location> */
    /** @param array<int, int>|null $locationIds */
    public function activeLocations(?array $locationIds = null): Collection;

    public function professionalIdForUser(int $userId): ?int;
}
