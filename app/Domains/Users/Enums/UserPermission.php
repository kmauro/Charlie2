<?php

namespace App\Domains\Users\Enums;

enum UserPermission: string
{
    case ManageAppointments = 'manage_appointments';
    case RecordPayments = 'record_payments';
    case AccessExternalClinicalRecord = 'access_external_clinical_record';
    case ViewAccounting = 'view_accounting';
    case ExportAudit = 'export_audit';

    public function label(): string
    {
        return match ($this) {
            self::ManageAppointments => 'Gestionar turnos',
            self::RecordPayments => 'Registrar cobros',
            self::AccessExternalClinicalRecord => 'Acceder a historia clínica externa',
            self::ViewAccounting => 'Acceder a contabilidad',
            self::ExportAudit => 'Exportar auditoría',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ManageAppointments => 'Consultar agendas, asignar, reprogramar y cancelar turnos.',
            self::RecordPayments => 'Cobrar prestaciones sin acceder a los módulos contables.',
            self::AccessExternalClinicalRecord => 'Abre la webapp externa cuando existe una relación asistencial vigente.',
            self::ViewAccounting => 'Consultar cierres de caja y balances mensuales.',
            self::ExportAudit => 'Permiso reservado a las funciones de auditoría.',
        };
    }

    /**
     * @return array<int, array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $permission): array => [
                'value' => $permission->value,
                'label' => $permission->label(),
                'description' => $permission->description(),
            ],
            self::cases(),
        );
    }
}
