<?php

namespace App\Domains\Patients\Repositories\Eloquent;

use App\Domains\Patients\Enums\PatientStatus;
use App\Domains\Patients\Models\Patient;
use App\Domains\Patients\Repositories\Contracts\PatientRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class EloquentPatientRepository implements PatientRepository
{
    /**
     * @param  array{search?: string, status?: string}  $filters
     * @return LengthAwarePaginator<int, Patient>
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return Patient::query()
            ->select([
                'id',
                'document_number',
                'first_name',
                'last_name',
                'birth_date',
                'phone',
                'coverage_name',
                'member_number',
                'status',
                'archived_at',
                'created_at',
                'updated_at',
            ])
            ->withCount('contacts')
            ->when(
                $filters['search'] ?? null,
                fn (Builder $query, string $search) => $query->where(
                    fn (Builder $nestedQuery) => $nestedQuery
                        ->where('document_number', 'like', "%$search%")
                        ->orWhere('first_name', 'like', "%$search%")
                        ->orWhere('last_name', 'like', "%$search%")
                        ->orWhereRaw(
                            "CONCAT(first_name, ' ', last_name) LIKE ?",
                            ["%$search%"],
                        )
                        ->orWhere('phone', 'like', "%$search%")
                        ->orWhere('email', 'like', "%$search%")
                        ->orWhere('coverage_name', 'like', "%$search%")
                        ->orWhere('member_number', 'like', "%$search%"),
                ),
            )
            ->when(
                $filters['status'] ?? null,
                fn (Builder $query, string $status) => $query->where('status', $status),
            )
            ->latest('id')
            ->paginate($perPage);
    }

    public function findOrFail(int $id): Patient
    {
        return Patient::query()
            ->with([
                'user.role',
                'contacts' => fn ($query) => $query
                    ->orderByDesc('is_legal_representative')
                    ->orderByDesc('is_emergency_contact')
                    ->orderBy('full_name'),
            ])
            ->findOrFail($id);
    }

    public function lockForUpdate(Patient $patient): Patient
    {
        return Patient::query()
            ->with([
                'user.role',
                'contacts' => fn ($query) => $query
                    ->orderByDesc('is_legal_representative')
                    ->orderByDesc('is_emergency_contact')
                    ->orderBy('full_name'),
            ])
            ->whereKey($patient->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * @return array{total: int, active: int, inpatient: int, archived: int, deceased: int, blocked: int}
     */
    public function summaries(): array
    {
        $countsByStatus = Patient::query()
            ->select('status')
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'total' => (int) $countsByStatus->sum(),
            'active' => (int) $countsByStatus->get(PatientStatus::Active->value, 0),
            'inpatient' => (int) $countsByStatus->get(PatientStatus::Inpatient->value, 0),
            'archived' => (int) $countsByStatus->get(PatientStatus::Archived->value, 0),
            'deceased' => (int) $countsByStatus->get(PatientStatus::Deceased->value, 0),
            'blocked' => (int) $countsByStatus->get(PatientStatus::Blocked->value, 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array<string, mixed>>  $contacts
     */
    public function create(array $attributes, array $contacts): Patient
    {
        $patient = Patient::query()->create($attributes);
        $patient->contacts()->createMany($contacts);

        return $this->findOrFail((int) $patient->getKey());
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array<string, mixed>>  $contacts
     */
    public function update(Patient $patient, array $attributes, array $contacts): Patient
    {
        $patient->update($attributes);
        $patient->contacts()->delete();
        $patient->contacts()->createMany($contacts);

        return $this->findOrFail((int) $patient->getKey());
    }

    public function setStatus(
        Patient $patient,
        PatientStatus $status,
        ?\DateTimeInterface $archivedAt,
        ?PatientStatus $previousStatus,
        ?\DateTimeInterface $portalAccessUntil,
    ): Patient {
        $patient->update([
            'status' => $status,
            'lock_version' => $patient->lock_version + 1,
            'previous_status' => $previousStatus,
            'archived_at' => $archivedAt,
            'portal_access_until' => $portalAccessUntil,
        ]);

        return $this->findOrFail((int) $patient->getKey());
    }
}
