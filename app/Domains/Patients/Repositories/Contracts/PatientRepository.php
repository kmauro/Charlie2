<?php

namespace App\Domains\Patients\Repositories\Contracts;

use App\Domains\Patients\Enums\PatientStatus;
use App\Domains\Patients\Models\Patient;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PatientRepository
{
    /**
     * @param  array{search?: string, status?: string}  $filters
     * @return LengthAwarePaginator<int, Patient>
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator;

    public function findOrFail(int $id): Patient;

    public function lockForUpdate(Patient $patient): Patient;

    /**
     * @return array{total: int, active: int, inpatient: int, archived: int, deceased: int, blocked: int}
     */
    public function summaries(): array;

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array<string, mixed>>  $contacts
     */
    public function create(array $attributes, array $contacts): Patient;

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array<string, mixed>>  $contacts
     */
    public function update(Patient $patient, array $attributes, array $contacts): Patient;

    public function setStatus(
        Patient $patient,
        PatientStatus $status,
        ?\DateTimeInterface $archivedAt,
        ?PatientStatus $previousStatus,
    ): Patient;
}
