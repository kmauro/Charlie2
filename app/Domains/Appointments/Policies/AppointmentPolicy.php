<?php

namespace App\Domains\Appointments\Policies;

use App\Domains\Appointments\Enums\AppointmentStatus;
use App\Domains\Appointments\Models\Appointment;
use App\Domains\Users\Enums\RoleCode;
use App\Domains\Users\Enums\UserPermission;
use App\Domains\Users\Models\User;

class AppointmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(UserPermission::ManageAppointments);
    }

    public function view(User $user, Appointment $appointment): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        if ($user->hasRole(RoleCode::SuperAdmin)) {
            return true;
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
        return $this->canAdministrativelyManage($user);
    }

    public function update(User $user, Appointment $appointment): bool
    {
        return $this->canAdministrativelyManage($user)
            && in_array($appointment->status, [
                AppointmentStatus::Reserved,
                AppointmentStatus::Confirmed,
            ], true);
    }

    public function changeStatus(User $user, Appointment $appointment): bool
    {
        return $this->canManage($user)
            && $this->isOwnedByProfessionalWhenRequired($user, $appointment)
            && $appointment->status->availableTransitions() !== [];
    }

    public function cancel(User $user, Appointment $appointment): bool
    {
        return $this->canAdministrativelyManage($user);
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
        return $user->hasPermission(UserPermission::ManageAppointments);
    }

    private function canAdministrativelyManage(User $user): bool
    {
        return $this->canManage($user)
            && ! $user->hasRole(RoleCode::Professional);
    }

    private function isOwnedByProfessionalWhenRequired(
        User $user,
        Appointment $appointment,
    ): bool {
        return ! $user->hasRole(RoleCode::Professional)
            || $appointment->professional()
                ->where('user_id', $user->getKey())
                ->exists();
    }
}
