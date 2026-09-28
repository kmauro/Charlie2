<?php

namespace App\Domains\Professionals\Policies;

use App\Domains\Users\Enums\RoleCode;
use App\Domains\Users\Enums\UserPermission;
use App\Domains\Users\Models\User;

class OperationalAgendaPolicy
{
    public function before(User $user): ?bool
    {
        return $user->hasRole(RoleCode::SuperAdmin) ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission(UserPermission::ManageAppointments);
    }

    public function manage(User $user): bool
    {
        return $user->hasPermission(UserPermission::ManageAppointments)
            && ($user->hasRole(RoleCode::Administrator)
                || $user->hasRole(RoleCode::Supervisor));
    }
}
