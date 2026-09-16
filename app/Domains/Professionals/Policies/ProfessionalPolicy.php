<?php

namespace App\Domains\Professionals\Policies;

use App\Domains\Professionals\Models\Professional;
use App\Domains\Users\Enums\RoleCode;
use App\Domains\Users\Models\User;

class ProfessionalPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($ability === 'forceDelete') {
            return false;
        }

        return $user->hasRole(RoleCode::SuperAdmin) ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, Professional $professional): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Professional $professional): bool
    {
        return false;
    }

    public function delete(User $user, Professional $professional): bool
    {
        return false;
    }

    public function restore(User $user, Professional $professional): bool
    {
        return false;
    }

    public function forceDelete(User $user, Professional $professional): bool
    {
        return false;
    }
}
