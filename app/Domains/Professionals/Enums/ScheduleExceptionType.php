<?php

namespace App\Domains\Professionals\Enums;

enum ScheduleExceptionType: string
{
    case Available = 'available';
    case Unavailable = 'unavailable';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Disponibilidad especial',
            self::Unavailable => 'Bloqueo excepcional',
        };
    }
}
