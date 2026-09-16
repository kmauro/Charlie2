<?php

namespace App\Domains\Organizations\Enums;

enum LocationService: string
{
    case Outpatient = 'outpatient';
    case Emergency = 'emergency';
    case Inpatient = 'inpatient';
    case Diagnostics = 'diagnostics';
    case Laboratory = 'laboratory';
    case Pharmacy = 'pharmacy';
    case CashDesk = 'cash_desk';

    public function label(): string
    {
        return match ($this) {
            self::Outpatient => 'Consultorios externos',
            self::Emergency => 'Guardia',
            self::Inpatient => 'Internación',
            self::Diagnostics => 'Diagnóstico por imágenes',
            self::Laboratory => 'Laboratorio',
            self::Pharmacy => 'Farmacia',
            self::CashDesk => 'Caja',
        };
    }
}
