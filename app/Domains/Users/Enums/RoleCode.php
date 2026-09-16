<?php

namespace App\Domains\Users\Enums;

enum RoleCode: string
{
    case SuperAdmin = 'superadmin';
    case Administrator = 'administrator';
    case Manager = 'manager';
    case Supervisor = 'supervisor';
    case Reception = 'reception';
    case Professional = 'professional';
    case Nursing = 'nursing';
    case Emergency = 'emergency';
    case Accounting = 'accounting';
    case Sales = 'sales';
    case Auditor = 'auditor';
    case Patient = 'patient';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Superadministrador',
            self::Administrator => 'Administrador',
            self::Manager => 'Gerente',
            self::Supervisor => 'Encargado',
            self::Reception => 'Recepción',
            self::Professional => 'Profesional',
            self::Nursing => 'Enfermería',
            self::Emergency => 'Guardia',
            self::Accounting => 'Contabilidad',
            self::Sales => 'Ventas',
            self::Auditor => 'Auditor',
            self::Patient => 'Paciente',
        };
    }
}
