<?php

namespace App\Domains\Patients\Enums;

enum PatientStatus: string
{
    case Active = 'active';
    case Inpatient = 'inpatient';
    case Archived = 'archived';
    case Deceased = 'deceased';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activo',
            self::Inpatient => 'Internado',
            self::Archived => 'Archivado',
            self::Deceased => 'Fallecido',
            self::Blocked => 'Bloqueado',
        };
    }

    /**
     * @return array<int, self>
     */
    public static function editableCases(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $status): bool => $status !== self::Archived,
        ));
    }
}
