<?php

namespace App\Domains\Organizations\Policies;

use App\Domains\Organizations\Models\Location;
use App\Domains\Users\Enums\RoleCode;
use App\Domains\Users\Models\User;

class LocationPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, Location $location): bool
    {
        return $this->canView($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, Location $location): bool
    {
        return ! $location->isArchived() && $this->canManage($user);
    }

    public function delete(User $user, Location $location): bool
    {
        return ! $location->isArchived() && $this->canManage($user);
    }

    public function restore(User $user, Location $location): bool
    {
        return $location->isArchived() && $this->canManage($user);
    }

    public function forceDelete(User $user, Location $location): bool
    {
        return false;
    }

    private function canView(User $user): bool
    {
        return in_array($user->role?->code, [
            RoleCode::SuperAdmin,
            RoleCode::Administrator,
            RoleCode::Manager,
            RoleCode::Supervisor,
        ], true);
    }

    private function canManage(User $user): bool
    {
        return in_array($user->role?->code, [
            RoleCode::SuperAdmin,
            RoleCode::Administrator,
        ], true);
    }
}
