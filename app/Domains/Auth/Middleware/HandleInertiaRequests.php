<?php

namespace App\Domains\Auth\Middleware;

use App\Domains\Organizations\Enums\OrganizationStatus;
use App\Domains\Organizations\Models\Location;
use App\Domains\Users\Enums\RoleCode;
use App\Domains\Users\Models\User;
use App\Domains\Users\Services\UserScopeService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'auth' => [
                'user' => $request->user() === null ? null : [
                    ...$request->user()->only('id', 'name', 'email'),
                    'role' => $request->user()->role === null ? null : [
                        'code' => $request->user()->role->code->value,
                        'name' => $request->user()->role->name,
                    ],
                ],
            ],
            'organizationContext' => fn (): array => $this->organizationContext($request),
            'interfaceMode' => true,
            'status' => fn (): mixed => $request->session()->get('status'),
        ];
    }

    /**
     * @return array{entities: array<int, array{id: int, name: string, locations: array<int, array{id: int, name: string}>}>, isLocked: bool, selectedLocationId: int|null}
     */
    private function organizationContext(Request $request): array
    {
        $actor = $request->user();

        if (! $actor instanceof User) {
            return ['entities' => [], 'isLocked' => true, 'selectedLocationId' => null];
        }

        $locationIds = app(UserScopeService::class)->locationIdsFor($actor);
        $locations = Location::query()
            ->with('legalEntity:id,business_name')
            ->where('status', OrganizationStatus::Active->value)
            ->when($locationIds !== null, fn ($query) => $query->whereKey($locationIds))
            ->orderBy('name')
            ->get(['id', 'legal_entity_id', 'name']);
        $entities = $locations
            ->groupBy('legal_entity_id')
            ->map(fn ($entityLocations): array => [
                'id' => (int) $entityLocations->first()->legal_entity_id,
                'name' => $entityLocations->first()->legalEntity->business_name,
                'locations' => $entityLocations
                    ->map(fn (Location $location): array => [
                        'id' => (int) $location->getKey(),
                        'name' => $location->name,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values();
        $requestedLocationId = (int) $request->session()->get(
            'selected_organization_location_id',
            0,
        );
        $selectedLocationId = $locations->contains('id', $requestedLocationId)
            ? $requestedLocationId
            : $locations->first()?->getKey();

        return [
            'entities' => $entities->all(),
            'isLocked' => $actor->hasRole(RoleCode::Patient)
                || ($entities->count() <= 1 && $locations->count() <= 1),
            'selectedLocationId' => $selectedLocationId === null
                ? null
                : (int) $selectedLocationId,
        ];
    }
}
