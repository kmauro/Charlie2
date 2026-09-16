<?php

namespace App\Domains\Patients\Enums;

enum RegisteredSex: string
{
    case Female = 'female';
    case Male = 'male';
    case X = 'x';
    case Unspecified = 'unspecified';

    public function label(): string
    {
        return match ($this) {
            self::Female => 'Femenino',
            self::Male => 'Masculino',
            self::X => 'X',
            self::Unspecified => 'No especificado',
        };
    }
}
