<?php

namespace App\Domains\Appointments\Policies;

use App\Domains\Appointments\Models\Appointment;
use App\Domains\Users\Enums\RoleCode;
use App\Domains\Users\Models\User;

class AppointmentPolicy
{
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

    public function view(User $user, Appointment $appointment): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        if (! $user->hasRole(RoleCode::Professional)) {
            return true;
        }

        return $appointment->professional()
            ->where('user_id', $user->getKey())
            ->exists();
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, Appointment $appointment): bool
    {
        return $this->canManage($user)
            && in_array($appointment->status, [
                \App\Domains\Appointments\Enums\AppointmentStatus::Reserved,
                \App\Domains\Appointments\Enums\AppointmentStatus::Confirmed,
            ], true);
    }

    public function changeStatus(User $user, Appointment $appointment): bool
    {
        return $this->canManage($user)
            && $appointment->status->availableTransitions() !== [];
    }

    public function cancel(User $user, Appointment $appointment): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, Appointment $appointment): bool
    {
        return false;
    }

    public function restore(User $user, Appointment $appointment): bool
    {
        return false;
    }

    public function forceDelete(User $user, Appointment $appointment): bool
    {
        return false;
    }

    private function canManage(User $user): bool
    {
        return $this->hasAnyRole($user, [
            RoleCode::SuperAdmin,
            RoleCode::Administrator,
            RoleCode::Manager,
            RoleCode::Supervisor,
            RoleCode::Reception,
        ]);
    }

    /** @param array<int, RoleCode> $roles */
    private function hasAnyRole(User $user, array $roles): bool
    {
        foreach ($roles as $role) {
            if ($user->hasRole($role)) {
                return true;
            }
        }

        return false;
    }
}
