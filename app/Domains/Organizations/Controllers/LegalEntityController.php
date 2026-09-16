<?php

namespace App\Domains\Organizations\Controllers;

use App\Domains\Audits\Data\AuditContext;
use App\Domains\Organizations\Enums\OrganizationStatus;
use App\Domains\Organizations\Models\LegalEntity;
use App\Domains\Organizations\Requests\StoreLegalEntityRequest;
use App\Domains\Organizations\Requests\UpdateLegalEntityRequest;
use App\Domains\Organizations\Services\OrganizationService;
use App\Domains\Users\Models\User;
use App\Domains\Users\Services\UserScopeService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LegalEntityController extends Controller
{
    public function __construct(
        private OrganizationService $service,
        private UserScopeService $scope,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', LegalEntity::class);
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(OrganizationStatus::class)],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);
        $filters = Arr::only($validated, ['search', 'status']);
        $actor = $this->authenticatedUser($request);
        $legalEntities = $this->service
            ->paginateLegalEntities(
                $filters,
                (int) ($validated['per_page'] ?? 15),
                $this->scope->legalEntityIdsFor($actor),
                $this->scope->locationIdsFor($actor),
            )
            ->appends(Arr::only($validated, ['search', 'status', 'per_page']));

        return Inertia::render('organizations/legal-entities/Index', [
            'legalEntities' => $legalEntities->through(
                fn (LegalEntity $legalEntity): array => $this->legalEntityData($legalEntity, $actor),
            ),
            'filters' => $filters,
            'statusOptions' => $this->statusOptions([
                ...OrganizationStatus::legalEntityEditableCases(),
                OrganizationStatus::Archived,
            ]),
            'permissions' => [
                'create' => Gate::forUser($actor)->allows('create', LegalEntity::class),
            ],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', LegalEntity::class);

        return Inertia::render('organizations/legal-entities/Create', [
            'statusOptions' => $this->statusOptions(
                OrganizationStatus::legalEntityEditableCases(),
            ),
        ]);
    }

    public function store(StoreLegalEntityRequest $request): RedirectResponse
    {
        Gate::authorize('create', LegalEntity::class);
        $this->service->createLegalEntity(
            $request->validated(),
            $this->authenticatedUser($request),
            $this->auditContext($request),
        );
        Inertia::flash('success', 'La razón social fue creada correctamente.');

        return redirect()->route('legal_entities.index');
    }

    public function edit(Request $request, LegalEntity $legalEntity): Response
    {
        Gate::authorize('update', $legalEntity);

        return Inertia::render('organizations/legal-entities/Edit', [
            'legalEntity' => $this->legalEntityData(
                $legalEntity,
                $this->authenticatedUser($request),
            ),
            'statusOptions' => $this->statusOptions(
                OrganizationStatus::legalEntityEditableCases(),
            ),
        ]);
    }

    public function update(
        UpdateLegalEntityRequest $request,
        LegalEntity $legalEntity,
    ): RedirectResponse {
        Gate::authorize('update', $legalEntity);
        $this->service->updateLegalEntity(
            $legalEntity,
            $request->validated(),
            $this->authenticatedUser($request),
            $this->auditContext($request),
        );
        Inertia::flash('success', 'La razón social fue actualizada correctamente.');

        return redirect()->route('legal_entities.index');
    }

    public function destroy(Request $request, LegalEntity $legalEntity): RedirectResponse
    {
        Gate::authorize('delete', $legalEntity);
        $this->service->archiveLegalEntity(
            $legalEntity,
            $this->authenticatedUser($request),
            $this->auditContext($request),
        );
        Inertia::flash('success', 'La razón social fue archivada.');

        return redirect()->route('legal_entities.index');
    }

    public function restore(Request $request, LegalEntity $legalEntity): RedirectResponse
    {
        Gate::authorize('restore', $legalEntity);
        $this->service->restoreLegalEntity(
            $legalEntity,
            $this->authenticatedUser($request),
            $this->auditContext($request),
        );
        Inertia::flash('success', 'La razón social fue restaurada.');

        return redirect()->route('legal_entities.index');
    }

    /** @param array<int, OrganizationStatus> $statuses @return array<int, array{value: string, label: string}> */
    private function statusOptions(array $statuses): array
    {
        return array_map(
            fn (OrganizationStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            $statuses,
        );
    }

    /** @return array<string, mixed> */
    private function legalEntityData(LegalEntity $legalEntity, User $actor): array
    {
        return [
            'id' => (int) $legalEntity->getKey(),
            'business_name' => $legalEntity->business_name,
            'cuit' => $legalEntity->cuit,
            'formatted_cuit' => $this->formatCuit($legalEntity->cuit),
            'fiscal_address' => $legalEntity->fiscal_address,
            'status' => [
                'value' => $legalEntity->status->value,
                'label' => $legalEntity->status->label(),
            ],
            'archived_at' => $legalEntity->archived_at?->toISOString(),
            'updated_at' => $legalEntity->updated_at->toISOString(),
            'locations_count' => (int) ($legalEntity->locations_count ?? 0),
            'active_locations_count' => (int) ($legalEntity->active_locations_count ?? 0),
            'unarchived_locations_count' => (int) ($legalEntity->unarchived_locations_count ?? 0),
            'permissions' => [
                'update' => Gate::forUser($actor)->allows('update', $legalEntity),
                'archive' => Gate::forUser($actor)->allows('delete', $legalEntity),
                'restore' => Gate::forUser($actor)->allows('restore', $legalEntity),
            ],
        ];
    }

    private function authenticatedUser(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function auditContext(Request $request): AuditContext
    {
        return new AuditContext(
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            correlationId: $request->headers->get('X-Correlation-ID'),
        );
    }

    private function formatCuit(string $cuit): string
    {
        return Str::substr($cuit, 0, 2).'-'.Str::substr($cuit, 2, 8).'-'.Str::substr($cuit, 10, 1);
    }
}
