<?php

namespace App\Domains\Organizations\Controllers;

use App\Domains\Audits\Data\AuditContext;
use App\Domains\Organizations\Enums\LocationService;
use App\Domains\Organizations\Enums\OrganizationStatus;
use App\Domains\Organizations\Models\LegalEntity;
use App\Domains\Organizations\Models\Location;
use App\Domains\Organizations\Requests\StoreLocationRequest;
use App\Domains\Organizations\Requests\UpdateLocationRequest;
use App\Domains\Organizations\Services\OrganizationService;
use App\Domains\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public function __construct(
        private OrganizationService $service,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Location::class);
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(OrganizationStatus::class)],
            'legal_entity_id' => ['nullable', 'integer', Rule::exists(LegalEntity::class, 'id')],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);
        $filters = Arr::only($validated, ['search', 'status', 'legal_entity_id']);
        $locations = $this->service
            ->paginateLocations($filters, (int) ($validated['per_page'] ?? 12))
            ->appends(Arr::only($validated, [
                'search',
                'status',
                'legal_entity_id',
                'per_page',
            ]));
        $actor = $this->authenticatedUser($request);

        return Inertia::render('organizations/views/Index', [
            'locations' => $locations->through(
                fn (Location $location): array => $this->locationData($location, $actor),
            ),
            'filters' => $filters,
            'summaries' => $this->service->summaries(),
            'legalEntities' => $this->legalEntityOptions(false),
            'statusOptions' => $this->statusOptions(OrganizationStatus::cases()),
            'permissions' => [
                'create' => Gate::forUser($actor)->allows('create', Location::class),
                'manage_legal_entities' => Gate::forUser($actor)->allows('create', LegalEntity::class),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Location::class);

        return Inertia::render('organizations/views/Create', [
            'legalEntities' => $this->legalEntityOptions(true),
            'statusOptions' => $this->statusOptions(
                OrganizationStatus::locationEditableCases(),
            ),
            'serviceOptions' => $this->serviceOptions(),
        ]);
    }

    public function store(StoreLocationRequest $request): RedirectResponse
    {
        Gate::authorize('create', Location::class);
        $this->service->createLocation(
            $request->validated(),
            $this->authenticatedUser($request),
            $this->auditContext($request),
        );
        Inertia::flash('success', 'La sede fue creada correctamente.');

        return redirect()->route('organizations.index');
    }

    public function edit(Request $request, Location $location): Response
    {
        Gate::authorize('update', $location);
        $location->loadMissing('legalEntity:id,business_name,cuit,status');

        return Inertia::render('organizations/views/Edit', [
            'location' => $this->locationData(
                $location,
                $this->authenticatedUser($request),
            ),
            'legalEntities' => $this->legalEntityOptions(true),
            'statusOptions' => $this->statusOptions(
                OrganizationStatus::locationEditableCases(),
            ),
            'serviceOptions' => $this->serviceOptions(),
        ]);
    }

    public function update(
        UpdateLocationRequest $request,
        Location $location,
    ): RedirectResponse {
        Gate::authorize('update', $location);
        $this->service->updateLocation(
            $location,
            $request->validated(),
            $this->authenticatedUser($request),
            $this->auditContext($request),
        );
        Inertia::flash('success', 'La sede fue actualizada correctamente.');

        return redirect()->route('organizations.index');
    }

    public function destroy(Request $request, Location $location): RedirectResponse
    {
        Gate::authorize('delete', $location);
        $this->service->archiveLocation(
            $location,
            $this->authenticatedUser($request),
            $this->auditContext($request),
        );
        Inertia::flash('success', 'La sede fue archivada.');

        return redirect()->route('organizations.index');
    }

    public function restore(Request $request, Location $location): RedirectResponse
    {
        Gate::authorize('restore', $location);
        $this->service->restoreLocation(
            $location,
            $this->authenticatedUser($request),
            $this->auditContext($request),
        );
        Inertia::flash('success', 'La sede fue restaurada.');

        return redirect()->route('organizations.index');
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

    /** @return array<int, array{value: string, label: string}> */
    private function serviceOptions(): array
    {
        return array_map(
            fn (LocationService $service): array => [
                'value' => $service->value,
                'label' => $service->label(),
            ],
            LocationService::cases(),
        );
    }

    /** @return array<int, array{id: int, label: string, cuit: string}> */
    private function legalEntityOptions(bool $onlyActive): array
    {
        return $this->service->legalEntities($onlyActive)
            ->map(fn (LegalEntity $legalEntity): array => [
                'id' => (int) $legalEntity->getKey(),
                'label' => $legalEntity->business_name,
                'cuit' => $this->formatCuit($legalEntity->cuit),
            ])
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function locationData(Location $location, User $actor): array
    {
        return [
            'id' => (int) $location->getKey(),
            'legal_entity_id' => $location->legal_entity_id,
            'legal_entity' => [
                'id' => (int) $location->legalEntity->getKey(),
                'business_name' => $location->legalEntity->business_name,
                'cuit' => $this->formatCuit($location->legalEntity->cuit),
            ],
            'name' => $location->name,
            'code' => $location->code,
            'address' => $location->address,
            'neighborhood' => $location->neighborhood,
            'postal_code' => $location->postal_code,
            'phone' => $location->phone,
            'email' => $location->email,
            'opening_date' => $location->opening_date?->toDateString(),
            'opening_time' => Str::substr((string) $location->opening_time, 0, 5),
            'closing_time' => Str::substr((string) $location->closing_time, 0, 5),
            'timezone' => $location->timezone,
            'services' => array_values($location->services ?? []),
            'service_labels' => array_map(
                fn (string $service): string => LocationService::from($service)->label(),
                $location->services ?? [],
            ),
            'status' => [
                'value' => $location->status->value,
                'label' => $location->status->label(),
            ],
            'archived_at' => $location->archived_at?->toISOString(),
            'updated_at' => $location->updated_at->toISOString(),
            'permissions' => [
                'update' => Gate::forUser($actor)->allows('update', $location),
                'archive' => Gate::forUser($actor)->allows('delete', $location),
                'restore' => Gate::forUser($actor)->allows('restore', $location),
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
