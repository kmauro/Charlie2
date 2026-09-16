<?php

namespace App\Domains\Users\Policies;

use App\Domains\Users\Enums\RoleCode;
use App\Domains\Users\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasRole(RoleCode::SuperAdmin);
    }

    public function view(User $actor, User $user): bool
    {
        return $actor->hasRole(RoleCode::SuperAdmin);
    }

    public function create(User $actor): bool
    {
        return $actor->hasRole(RoleCode::SuperAdmin);
    }

    public function update(User $actor, User $user): bool
    {
        return $actor->hasRole(RoleCode::SuperAdmin);
    }

    public function delete(User $actor, User $user): bool
    {
        return $actor->hasRole(RoleCode::SuperAdmin);
    }

    public function restore(User $actor, User $user): bool
    {
        return $actor->hasRole(RoleCode::SuperAdmin);
    }
}
