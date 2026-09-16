<?php

namespace App\Domains\Organizations\Enums;

enum OrganizationStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Preparation = 'preparation';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activa',
            self::Inactive => 'Inactiva',
            self::Preparation => 'En preparación',
            self::Archived => 'Archivada',
        };
    }

    /** @return array<int, self> */
    public static function legalEntityEditableCases(): array
    {
        return [self::Active, self::Inactive];
    }

    /** @return array<int, self> */
    public static function locationEditableCases(): array
    {
        return [self::Active, self::Inactive, self::Preparation];
    }
}
