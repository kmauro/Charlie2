<?php

namespace App\Domains\ExternalClinicalRecords\Controllers;

use App\Domains\Audits\Data\AuditContext;
use App\Domains\Audits\Services\AuditService;
use App\Domains\ExternalClinicalRecords\Services\ExternalClinicalRecordLinkService;
use App\Domains\Patients\Models\Patient;
use App\Domains\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class ExternalClinicalRecordController extends Controller
{
    public function __construct(
        private ExternalClinicalRecordLinkService $link,
        private AuditService $audit,
    ) {}

    public function __invoke(
        Request $request,
        Patient $patient,
    ): RedirectResponse|Response {
        $actor = $this->authenticatedUser($request);
        $auditContext = $this->auditContext($request);

        if (Gate::forUser($actor)->denies('accessExternalClinicalRecord', $patient)) {
            $this->audit->record(
                event: 'external_clinical_record.access_denied',
                auditable: $patient,
                actor: $actor,
                context: new AuditContext(
                    ipAddress: $auditContext->ipAddress,
                    userAgent: $auditContext->userAgent,
                    outcome: 'denied',
                    reason: 'No existe una relación asistencial vigente.',
                    correlationId: $auditContext->correlationId,
                ),
            );

            abort(403);
        }

        $externalUrl = $this->link->url();

        if ($externalUrl === null) {
            $this->audit->record(
                event: 'external_clinical_record.configuration_failed',
                auditable: $patient,
                actor: $actor,
                context: new AuditContext(
                    ipAddress: $auditContext->ipAddress,
                    userAgent: $auditContext->userAgent,
                    outcome: 'failure',
                    reason: 'La URL externa no está configurada con HTTPS.',
                    correlationId: $auditContext->correlationId,
                ),
            );

            abort(503, 'La historia clínica externa no está disponible.');
        }

        $this->audit->record(
            event: 'external_clinical_record.opened',
            auditable: $patient,
            afterValues: [
                'destination_host' => parse_url($externalUrl, PHP_URL_HOST),
            ],
            actor: $actor,
            context: $auditContext,
        );

        if ($request->hasHeader('X-Inertia')) {
            return Inertia::location($externalUrl);
        }

        return redirect()->away($externalUrl);
    }

    private function auditContext(Request $request): AuditContext
    {
        $correlationId = $request->header('X-Request-ID');

        return new AuditContext(
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            correlationId: is_string($correlationId) ? $correlationId : null,
        );
    }

    private function authenticatedUser(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }
}
