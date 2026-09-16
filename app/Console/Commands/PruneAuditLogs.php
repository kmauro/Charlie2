<?php

namespace App\Console\Commands;

use App\Domains\Audits\Mail\AuditRetentionWarningMail;
use App\Domains\Audits\Models\AuditLog;
use App\Domains\Audits\Repositories\Contracts\AuditLogRepository;
use App\Domains\Audits\Services\AuditService;
use App\Domains\Users\Enums\RoleCode;
use App\Domains\Users\Enums\UserPermission;
use App\Domains\Users\Enums\UserStatus;
use App\Domains\Users\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

#[Signature('audits:prune {--dry-run : Informa el alcance sin enviar correos ni eliminar registros}')]
#[Description('Notifica a los auditores y elimina registros de auditoría con más de un año')]
class PruneAuditLogs extends Command
{
    public function __construct(
        private AuditLogRepository $auditLogs,
        private AuditService $audit,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $cutoff = now()->subYear();
        $recordsCount = $this->auditLogs->countCreatedBefore($cutoff);

        if ($recordsCount === 0) {
            $this->info('No hay registros de auditoría vencidos.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("Se eliminarían {$recordsCount} registros de auditoría.");

            return self::SUCCESS;
        }

        $auditors = User::query()
            ->with('role')
            ->where('status', UserStatus::Active->value)
            ->whereHas('role', fn ($query) => $query->where('code', RoleCode::Auditor->value))
            ->get()
            ->filter(fn (User $user): bool => $user->hasPermission(UserPermission::ExportAudit));

        if ($auditors->isEmpty()) {
            $this->error('La limpieza se canceló porque no hay auditores activos para notificar.');

            return self::FAILURE;
        }

        foreach ($auditors as $auditor) {
            Mail::to($auditor->email)->send(new AuditRetentionWarningMail(
                recordsCount: $recordsCount,
                cutoff: $cutoff,
            ));
        }

        $deletedCount = $this->auditLogs->deleteCreatedBefore($cutoff);

        $this->audit->recordResource(
            event: 'audit.retention_pruned',
            auditableType: AuditLog::class,
            afterValues: [
                'deleted_count' => $deletedCount,
                'cutoff' => $cutoff->toISOString(),
            ],
        );

        $this->info("Se eliminaron {$deletedCount} registros de auditoría.");

        return self::SUCCESS;
    }
}
