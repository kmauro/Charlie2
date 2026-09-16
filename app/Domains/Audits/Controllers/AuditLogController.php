<?php

namespace App\Domains\Audits\Controllers;

use App\Domains\Audits\Data\AuditContext;
use App\Domains\Audits\Models\AuditLog;
use App\Domains\Audits\Requests\FilterAuditLogsRequest;
use App\Domains\Audits\Services\AuditLogQueryService;
use App\Domains\Audits\Services\AuditService;
use App\Domains\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function __construct(
        private AuditLogQueryService $query,
        private AuditService $audit,
    ) {}

    public function index(FilterAuditLogsRequest $request): Response
    {
        $filters = Arr::except($request->validated(), ['per_page']);
        $logs = $this->query->paginate(
            $filters,
            (int) $request->validated('per_page', 25),
        );
        $actor = $this->authenticatedUser($request);

        $this->audit->recordResource(
            event: 'audit.index.viewed',
            auditableType: AuditLog::class,
            actor: $actor,
            context: AuditContext::fromRequest($request),
        );

        return Inertia::render('audits/views/Index', [
            'logs' => $logs->through(fn (AuditLog $log): array => $this->logData($log)),
            'filters' => $filters,
            'actors' => $this->query->actors()->map(fn (User $user): array => [
                'value' => (int) $user->getKey(),
                'label' => $user->name,
            ])->all(),
            'canExport' => $actor->can('export', AuditLog::class),
        ]);
    }

    public function export(FilterAuditLogsRequest $request): StreamedResponse
    {
        Gate::authorize('export', AuditLog::class);
        $filters = Arr::except($request->validated(), ['per_page']);
        $actor = $this->authenticatedUser($request);

        $this->audit->recordResource(
            event: 'audit.exported',
            auditableType: AuditLog::class,
            afterValues: ['filters' => array_keys(array_filter($filters))],
            actor: $actor,
            context: AuditContext::fromRequest($request),
        );

        return response()->streamDownload(function () use ($filters): void {
            $stream = fopen('php://output', 'wb');

            if ($stream === false) {
                return;
            }

            fputcsv($stream, [
                'Fecha',
                'Actor',
                'Evento',
                'Recurso',
                'ID de recurso',
                'Resultado',
                'Correlación',
                'IP',
            ]);

            $this->query->exportQuery($filters)->lazyById()->each(
                function (AuditLog $log) use ($stream): void {
                    fputcsv($stream, array_map(
                        fn (?string $value): string => $this->safeCsvValue($value),
                        [
                            $log->created_at->format('d/m/Y H:i:s'),
                            $log->actor?->name,
                            $log->event,
                            class_basename($log->auditable_type),
                            $log->auditable_id,
                            $log->outcome,
                            $log->correlation_id,
                            $log->ip_address,
                        ],
                    ));
                },
            );

            fclose($stream);
        }, 'auditoria-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /** @return array<string, mixed> */
    private function logData(AuditLog $log): array
    {
        return [
            'id' => (int) $log->getKey(),
            'created_at' => $log->created_at->format('d/m/Y H:i:s'),
            'actor' => $log->actor?->name ?? 'Sistema',
            'event' => $log->event,
            'resource' => class_basename($log->auditable_type),
            'resource_id' => $log->auditable_id,
            'outcome' => $log->outcome,
            'correlation_id' => $log->correlation_id,
            'ip_address' => $log->ip_address,
        ];
    }

    private function safeCsvValue(?string $value): string
    {
        $value ??= '';

        return Str::startsWith($value, ['=', '+', '-', '@'])
            ? "'$value"
            : $value;
    }

    private function authenticatedUser(FilterAuditLogsRequest $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }
}
