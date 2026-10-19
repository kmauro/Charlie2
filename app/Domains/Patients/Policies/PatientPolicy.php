<?php

namespace App\Domains\Patients\Policies;

use App\Domains\ExternalClinicalRecords\Services\ExternalClinicalAccessService;
use App\Domains\Patients\Models\Patient;
use App\Domains\Users\Enums\RoleCode;
use App\Domains\Users\Models\User;

class PatientPolicy
{
    public function __construct(
        private ExternalClinicalAccessService $externalClinicalAccess,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->hasAnyRole($user, [
            RoleCode::SuperAdmin,
            RoleCode::Administrator,
            RoleCode::Manager,
            RoleCode::Supervisor,
            RoleCode::Reception,
            RoleCode::Professional,
            RoleCode::Nursing,
            RoleCode::Emergency,
        ]);
    }

    public function view(User $user, Patient $patient): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->canManageAdministrativeData($user);
    }

    public function update(User $user, Patient $patient): bool
    {
        return ! $patient->isArchived()
            && $this->canManageAdministrativeData($user);
    }

    public function delete(User $user, Patient $patient): bool
    {
        return ! $patient->isArchived()
            && $this->canArchive($user);
    }

    public function restore(User $user, Patient $patient): bool
    {
        return $patient->isArchived()
            && $this->canArchive($user);
    }

    public function forceDelete(User $user, Patient $patient): bool
    {
        return false;
    }

    public function manageStatus(User $user, ?Patient $patient = null): bool
    {
        return $this->hasAnyRole($user, [
            RoleCode::SuperAdmin,
            RoleCode::Administrator,
            RoleCode::Manager,
            RoleCode::Supervisor,
        ]);
    }

    public function accessExternalClinicalRecord(User $user, Patient $patient): bool
    {
        return $this->externalClinicalAccess->canAccessPatient($user, $patient);
    }

    private function canManageAdministrativeData(User $user): bool
    {
        return $this->hasAnyRole($user, [
            RoleCode::SuperAdmin,
            RoleCode::Administrator,
            RoleCode::Manager,
            RoleCode::Supervisor,
            RoleCode::Reception,
        ]);
    }

    private function canArchive(User $user): bool
    {
        return $this->hasAnyRole($user, [
            RoleCode::SuperAdmin,
            RoleCode::Administrator,
            RoleCode::Manager,
            RoleCode::Supervisor,
            RoleCode::Reception,
        ]);
    }

    /**
     * @param  array<int, RoleCode>  $roles
     */
    private function hasAnyRole(User $user, array $roles): bool
    {
        return in_array($user->role?->code, $roles, true);
    }
}
