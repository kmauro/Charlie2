<?php

namespace App\Domains\Operations\Policies;

use App\Domains\Operations\Models\Admission;
use App\Domains\Users\Enums\UserPermission;
use App\Domains\Users\Models\User;
use App\Domains\Users\Services\UserScopeService;

class AdmissionPolicy
{
    public function __construct(private UserScopeService $scope) {}
    public function viewAny(User $user): bool { return $user->hasPermission(UserPermission::ViewInpatientCensus) || $user->hasPermission(UserPermission::ManageAdmissions); }
    public function view(User $user, Admission $admission): bool { return $this->viewAny($user) && $this->scope->canAccessLocation($user, $admission->location_id); }
    public function create(User $user): bool { return $user->hasPermission(UserPermission::ManageAdmissions); }
    public function update(User $user, Admission $admission): bool { return $user->hasPermission(UserPermission::ManageAdmissions) && $this->scope->canAccessLocation($user, $admission->location_id); }
    public function confirmReception(User $user, Admission $admission): bool { return $user->hasPermission(UserPermission::ConfirmBedReception) && $this->scope->canAccessLocation($user, $admission->location_id); }
}
