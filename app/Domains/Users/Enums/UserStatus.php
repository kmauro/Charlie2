<?php

namespace App\Domains\Users\Enums;

enum UserStatus: string
{
    case PendingInvitation = 'pending_invitation';
    case Active = 'active';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::PendingInvitation => 'Invitación pendiente',
            self::Active => 'Activo',
            self::Suspended => 'Suspendido',
        };
    }
}
