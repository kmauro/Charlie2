<?php

namespace App\Domains\Professionals\Enums;

enum ProfessionalStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case OnLeave = 'on_leave';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activo',
            self::Inactive => 'Inactivo',
            self::OnLeave => 'Licencia',
        };
    }
}
