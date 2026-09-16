<?php

namespace App\Domains\Organizations\Policies;

use App\Domains\Organizations\Models\LegalEntity;
use App\Domains\Users\Enums\RoleCode;
use App\Domains\Users\Models\User;
use App\Domains\Users\Services\UserScopeService;

class LegalEntityPolicy
{
    public function __construct(private UserScopeService $scope) {}

    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, LegalEntity $legalEntity): bool
    {
        return $this->canView($user)
            && $this->scope->canAccessLegalEntity($user, (int) $legalEntity->getKey());
    }

    public function create(User $user): bool
    {
        return $user->hasRole(RoleCode::SuperAdmin);
    }

    public function update(User $user, LegalEntity $legalEntity): bool
    {
        return ! $legalEntity->isArchived() && $this->canManage($user)
            && $this->scope->canAccessLegalEntity($user, (int) $legalEntity->getKey());
    }

    public function delete(User $user, LegalEntity $legalEntity): bool
    {
        return ! $legalEntity->isArchived() && $this->canManage($user)
            && $this->scope->canAccessLegalEntity($user, (int) $legalEntity->getKey());
    }

    public function restore(User $user, LegalEntity $legalEntity): bool
    {
        return $legalEntity->isArchived() && $this->canManage($user)
            && $this->scope->canAccessLegalEntity($user, (int) $legalEntity->getKey());
    }

    public function forceDelete(User $user, LegalEntity $legalEntity): bool
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
