<?php

namespace App\Domains\Audits\Policies;

use App\Domains\Audits\Models\AuditLog;
use App\Domains\Users\Enums\RoleCode;
use App\Domains\Users\Enums\UserPermission;
use App\Domains\Users\Models\User;

class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(RoleCode::Auditor);
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        return $this->viewAny($user);
    }

    public function export(User $user): bool
    {
        return $user->hasRole(RoleCode::Auditor)
            && $user->hasPermission(UserPermission::ExportAudit);
    }
}
