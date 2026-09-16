<?php

namespace App\Domains\Organizations\Repositories\Contracts;

use App\Domains\Organizations\Enums\OrganizationStatus;
use App\Domains\Organizations\Models\LegalEntity;
use App\Domains\Organizations\Models\Location;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface OrganizationRepository
{
    /**
     * @param  array{search?: string, status?: string, legal_entity_id?: int|string}  $filters
     * @return LengthAwarePaginator<int, Location>
     */
    public function paginateLocations(array $filters, int $perPage): LengthAwarePaginator;

    /**
     * @param  array{search?: string, status?: string}  $filters
     * @return LengthAwarePaginator<int, LegalEntity>
     */
    public function paginateLegalEntities(array $filters, int $perPage): LengthAwarePaginator;

    /** @return Collection<int, LegalEntity> */
    public function legalEntities(bool $onlyActive = false): Collection;

    /** @return array{locations_total: int, locations_active: int, locations_preparation: int, locations_archived: int, legal_entities_total: int, legal_entities_active: int} */
    public function summaries(): array;

    public function findLegalEntityOrFail(int $id): LegalEntity;

    public function lockLegalEntityForUpdate(LegalEntity $legalEntity): LegalEntity;

    public function lockLocationForUpdate(Location $location): Location;

    public function hasUnarchivedLocations(LegalEntity $legalEntity): bool;

    public function hasActiveLocations(LegalEntity $legalEntity): bool;

    /** @param array<string, mixed> $attributes */
    public function createLegalEntity(array $attributes): LegalEntity;

    /** @param array<string, mixed> $attributes */
    public function updateLegalEntity(LegalEntity $legalEntity, array $attributes): LegalEntity;

    public function setLegalEntityStatus(
        LegalEntity $legalEntity,
        OrganizationStatus $status,
        ?\DateTimeInterface $archivedAt,
    ): LegalEntity;

    /** @param array<string, mixed> $attributes */
    public function createLocation(array $attributes): Location;

    /** @param array<string, mixed> $attributes */
    public function updateLocation(Location $location, array $attributes): Location;

    public function setLocationStatus(
        Location $location,
        OrganizationStatus $status,
        ?\DateTimeInterface $archivedAt,
    ): Location;
}
