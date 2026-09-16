<?php

namespace App\Domains\Appointments\Enums;

enum AppointmentType: string
{
    case InPerson = 'in_person';
    case Virtual = 'virtual';
    case Emergency = 'emergency';
    case Procedure = 'procedure';

    public function label(): string
    {
        return match ($this) {
            self::InPerson => 'Presencial',
            self::Virtual => 'Virtual',
            self::Emergency => 'Guardia',
            self::Procedure => 'Procedimiento',
        };
    }
}
