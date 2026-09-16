<?php

namespace App\Domains\Professionals\Enums;

enum OfficeAssignmentType: string
{
    case Availability = 'availability';
    case Fixed = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::Availability => 'Según disponibilidad',
            self::Fixed => 'Consultorio fijo',
        };
    }
}
