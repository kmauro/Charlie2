<?php

namespace App\Domains\Organizations\Repositories\Eloquent;

use App\Domains\Organizations\Enums\OrganizationStatus;
use App\Domains\Organizations\Models\LegalEntity;
use App\Domains\Organizations\Models\Location;
use App\Domains\Organizations\Repositories\Contracts\OrganizationRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class EloquentOrganizationRepository implements OrganizationRepository
{
    /**
     * @param  array{search?: string, status?: string, legal_entity_id?: int|string}  $filters
     * @return LengthAwarePaginator<int, Location>
     */
    public function paginateLocations(array $filters, int $perPage): LengthAwarePaginator
    {
        return Location::query()
            ->select([
                'id',
                'legal_entity_id',
                'name',
                'code',
                'address',
                'neighborhood',
                'postal_code',
                'phone',
                'email',
                'opening_date',
                'opening_time',
                'closing_time',
                'timezone',
                'services',
                'status',
                'archived_at',
                'updated_at',
            ])
            ->with('legalEntity:id,business_name,cuit,status')
            ->when(
                array_key_exists('_location_ids', $filters),
                fn (Builder $query) => $query->whereKey($filters['_location_ids']),
            )
            ->when(
                $filters['search'] ?? null,
                fn (Builder $query, string $search) => $query->where(
                    fn (Builder $nestedQuery) => $nestedQuery
                        ->where('name', 'like', "%$search%")
                        ->orWhere('code', 'like', "%$search%")
                        ->orWhere('address', 'like', "%$search%")
                        ->orWhere('neighborhood', 'like', "%$search%")
                        ->orWhere('phone', 'like', "%$search%")
                        ->orWhere('email', 'like', "%$search%")
                        ->orWhereHas(
                            'legalEntity',
                            fn (Builder $legalEntityQuery) => $legalEntityQuery
                                ->where('business_name', 'like', "%$search%")
                                ->orWhere('cuit', 'like', "%$search%"),
                        ),
                ),
            )
            ->when(
                $filters['status'] ?? null,
                fn (Builder $query, string $status) => $query->where('status', $status),
            )
            ->when(
                $filters['legal_entity_id'] ?? null,
                fn (Builder $query, int|string $legalEntityId) => $query
                    ->where('legal_entity_id', $legalEntityId),
            )
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * @param  array{search?: string, status?: string}  $filters
     * @return LengthAwarePaginator<int, LegalEntity>
     */
    public function paginateLegalEntities(array $filters, int $perPage): LengthAwarePaginator
    {
        return LegalEntity::query()
            ->select([
                'id',
                'business_name',
                'cuit',
                'fiscal_address',
                'status',
                'archived_at',
                'updated_at',
            ])
            ->withCount([
                'locations' => fn (Builder $query) => $query->when(
                    array_key_exists('_location_ids', $filters),
                    fn (Builder $scopeQuery) => $scopeQuery->whereKey($filters['_location_ids']),
                ),
                'locations as active_locations_count' => fn (Builder $query) => $query
                    ->when(
                        array_key_exists('_location_ids', $filters),
                        fn (Builder $scopeQuery) => $scopeQuery->whereKey($filters['_location_ids']),
                    )
                    ->where('status', OrganizationStatus::Active->value),
                'locations as unarchived_locations_count' => fn (Builder $query) => $query
                    ->when(
                        array_key_exists('_location_ids', $filters),
                        fn (Builder $scopeQuery) => $scopeQuery->whereKey($filters['_location_ids']),
                    )
                    ->where('status', '!=', OrganizationStatus::Archived->value),
            ])
            ->when(
                array_key_exists('_legal_entity_ids', $filters),
                fn (Builder $query) => $query->whereKey($filters['_legal_entity_ids']),
            )
            ->when(
                $filters['search'] ?? null,
                fn (Builder $query, string $search) => $query->where(
                    fn (Builder $nestedQuery) => $nestedQuery
                        ->where('business_name', 'like', "%$search%")
                        ->orWhere('cuit', 'like', "%$search%")
                        ->orWhere('fiscal_address', 'like', "%$search%"),
                ),
            )
            ->when(
                $filters['status'] ?? null,
                fn (Builder $query, string $status) => $query->where('status', $status),
            )
            ->latest('id')
            ->paginate($perPage);
    }

    /** @return Collection<int, LegalEntity> */
    public function legalEntities(bool $onlyActive = false): Collection
    {
        return LegalEntity::query()
            ->select(['id', 'business_name', 'cuit', 'status'])
            ->when(
                $onlyActive,
                fn (Builder $query) => $query->where(
                    'status',
                    OrganizationStatus::Active->value,
                ),
            )
            ->orderBy('business_name')
            ->get();
    }

    public function summaries(?array $locationIds = null, ?array $legalEntityIds = null): array
    {
        $locationCounts = Location::query()
            ->when(
                $locationIds !== null,
                fn (Builder $query) => $query->whereKey($locationIds),
            )
            ->select('status')
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $legalEntityCounts = LegalEntity::query()
            ->when(
                $legalEntityIds !== null,
                fn (Builder $query) => $query->whereKey($legalEntityIds),
            )
            ->select('status')
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'locations_total' => (int) $locationCounts->sum(),
            'locations_active' => (int) $locationCounts->get(OrganizationStatus::Active->value, 0),
            'locations_preparation' => (int) $locationCounts->get(OrganizationStatus::Preparation->value, 0),
            'locations_archived' => (int) $locationCounts->get(OrganizationStatus::Archived->value, 0),
            'legal_entities_total' => (int) $legalEntityCounts->sum(),
            'legal_entities_active' => (int) $legalEntityCounts->get(OrganizationStatus::Active->value, 0),
        ];
    }

    public function findLegalEntityOrFail(int $id): LegalEntity
    {
        return LegalEntity::query()->findOrFail($id);
    }

    public function lockLegalEntityForUpdate(LegalEntity $legalEntity): LegalEntity
    {
        return LegalEntity::query()
            ->whereKey($legalEntity->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    public function lockLocationForUpdate(Location $location): Location
    {
        return Location::query()
            ->with('legalEntity:id,business_name,cuit,status')
            ->whereKey($location->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    public function hasUnarchivedLocations(LegalEntity $legalEntity): bool
    {
        return $legalEntity->locations()
            ->where('status', '!=', OrganizationStatus::Archived->value)
            ->exists();
    }

    public function hasActiveLocations(LegalEntity $legalEntity): bool
    {
        return $legalEntity->locations()
            ->where('status', OrganizationStatus::Active->value)
            ->exists();
    }

    public function createLegalEntity(array $attributes): LegalEntity
    {
        return LegalEntity::query()->create($attributes);
    }

    public function updateLegalEntity(LegalEntity $legalEntity, array $attributes): LegalEntity
    {
        $legalEntity->update($attributes);

        return $legalEntity->refresh();
    }

    public function setLegalEntityStatus(
        LegalEntity $legalEntity,
        OrganizationStatus $status,
        ?\DateTimeInterface $archivedAt,
    ): LegalEntity {
        $legalEntity->update([
            'status' => $status,
            'archived_at' => $archivedAt,
        ]);

        return $legalEntity->refresh();
    }

    public function createLocation(array $attributes): Location
    {
        $location = Location::query()->create($attributes);

        return $location->load('legalEntity:id,business_name,cuit,status');
    }

    public function updateLocation(Location $location, array $attributes): Location
    {
        $location->update($attributes);

        return $location->refresh()->load('legalEntity:id,business_name,cuit,status');
    }

    public function setLocationStatus(
        Location $location,
        OrganizationStatus $status,
        ?\DateTimeInterface $archivedAt,
    ): Location {
        $location->update([
            'status' => $status,
            'archived_at' => $archivedAt,
        ]);

        return $location->refresh()->load('legalEntity:id,business_name,cuit,status');
    }
}
