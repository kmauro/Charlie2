<?php

namespace App\Domains\Operations\Policies;

use App\Domains\Operations\Models\EmergencyVisit;
use App\Domains\Users\Enums\UserPermission;
use App\Domains\Users\Models\User;
use App\Domains\Users\Services\UserScopeService;

class EmergencyVisitPolicy
{
    public function __construct(private UserScopeService $scope) {}
    public function viewAny(User $user): bool { return $user->hasPermission(UserPermission::ManageEmergencyVisits); }
    public function view(User $user, EmergencyVisit $visit): bool { return $this->viewAny($user) && $this->scope->canAccessLocation($user, $visit->location_id); }
    public function create(User $user): bool { return $this->viewAny($user); }
    public function update(User $user, EmergencyVisit $visit): bool { return $this->view($user, $visit); }
}
