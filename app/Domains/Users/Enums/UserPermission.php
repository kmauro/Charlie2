<?php

namespace App\Domains\Users\Enums;

enum UserPermission: string
{
    case ManageAppointments = 'manage_appointments';
    case RecordPayments = 'record_payments';
    case AccessExternalClinicalRecord = 'access_external_clinical_record';
    case ViewAccounting = 'view_accounting';
    case ExportAudit = 'export_audit';
    case ManageEmergencyVisits = 'manage_emergency_visits';
    case ManageAdmissions = 'manage_admissions';
    case ViewInpatientCensus = 'view_inpatient_census';
    case ConfirmBedReception = 'confirm_bed_reception';

    /** @return array<int, RoleCode> */
    public function allowedRoles(): array
    {
        return match ($this) {
            self::ManageAppointments => [
                RoleCode::Administrator,
                RoleCode::Supervisor,
                RoleCode::Reception,
                RoleCode::Professional,
            ],
            self::RecordPayments => [
                RoleCode::Administrator,
                RoleCode::Supervisor,
                RoleCode::Reception,
                RoleCode::Sales,
            ],
            self::AccessExternalClinicalRecord => [
                RoleCode::Supervisor,
                RoleCode::Professional,
            ],
            self::ViewAccounting => [
                RoleCode::Manager,
                RoleCode::Accounting,
            ],
            self::ExportAudit => [RoleCode::Auditor],
            self::ManageEmergencyVisits => [RoleCode::Administrator, RoleCode::Supervisor, RoleCode::Nursing, RoleCode::Emergency],
            self::ManageAdmissions => [RoleCode::Administrator, RoleCode::Supervisor],
            self::ViewInpatientCensus => [RoleCode::Administrator, RoleCode::Supervisor, RoleCode::Nursing, RoleCode::Emergency],
            self::ConfirmBedReception => [RoleCode::Nursing],
        };
    }

    public function isAllowedFor(RoleCode $role): bool
    {
        return in_array($role, $this->allowedRoles(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::ManageAppointments => 'Gestionar turnos',
            self::RecordPayments => 'Registrar cobros',
            self::AccessExternalClinicalRecord => 'Acceder a historia clínica externa',
            self::ViewAccounting => 'Acceder a contabilidad',
            self::ExportAudit => 'Exportar auditoría',
            self::ManageEmergencyVisits => 'Gestionar guardia',
            self::ManageAdmissions => 'Gestionar internaciones',
            self::ViewInpatientCensus => 'Consultar censo de internación',
            self::ConfirmBedReception => 'Confirmar recepción de traslado',
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
            self::ManageEmergencyVisits => 'Registra, inicia, cierra y anula ingresos administrativos de guardia.',
            self::ManageAdmissions => 'Administra camas, internaciones, traslados y egresos.',
            self::ViewInpatientCensus => 'Consulta el censo operativo por sede y sector.',
            self::ConfirmBedReception => 'Confirma la recepción administrativa de traslados.',
        };
    }

    /**
     * @return array<int, array{value: string, label: string, description: string, allowed_roles: array<int, string>}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $permission): array => [
                'value' => $permission->value,
                'label' => $permission->label(),
                'description' => $permission->description(),
                'allowed_roles' => array_map(
                    fn (RoleCode $role): string => $role->value,
                    $permission->allowedRoles(),
                ),
            ],
            self::cases(),
        );
    }
}
