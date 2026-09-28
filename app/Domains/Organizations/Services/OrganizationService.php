<?php

namespace App\Domains\Organizations\Services;

use App\Domains\Audits\Data\AuditContext;
use App\Domains\Audits\Services\AuditService;
use App\Domains\Billing\Services\BillingSetupService;
use App\Domains\Organizations\Enums\OrganizationStatus;
use App\Domains\Organizations\Models\LegalEntity;
use App\Domains\Organizations\Models\Location;
use App\Domains\Organizations\Repositories\Contracts\OrganizationRepository;
use App\Domains\Users\Models\User;
use App\Domains\Users\Services\UserScopeService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrganizationService
{
    public function __construct(
        private OrganizationRepository $organizations,
        private AuditService $audit,
        private UserScopeService $scope,
        private BillingSetupService $billingSetup,
    ) {}

    /**
     * @param  array{search?: string, status?: string, legal_entity_id?: int|string}  $filters
     * @return LengthAwarePaginator<int, Location>
     */
    public function paginateLocations(
        array $filters = [],
        int $perPage = 15,
        ?array $locationIds = null,
    ): LengthAwarePaginator {
        $normalizedFilters = Arr::only(
            $filters,
            ['search', 'status', 'legal_entity_id'],
        );

        if (isset($normalizedFilters['search'])) {
            $normalizedFilters['search'] = Str::squish($normalizedFilters['search']);
        }

        if ($locationIds !== null) {
            $normalizedFilters['_location_ids'] = $locationIds;
        }

        return $this->organizations->paginateLocations(
            array_filter(
                $normalizedFilters,
                fn (mixed $value): bool => $value !== null && $value !== '',
            ),
            min(max($perPage, 1), 100),
        );
    }

    /**
     * @param  array{search?: string, status?: string}  $filters
     * @return LengthAwarePaginator<int, LegalEntity>
     */
    public function paginateLegalEntities(
        array $filters = [],
        int $perPage = 15,
        ?array $legalEntityIds = null,
        ?array $locationIds = null,
    ): LengthAwarePaginator {
        $normalizedFilters = Arr::only($filters, ['search', 'status']);

        if (isset($normalizedFilters['search'])) {
            $normalizedFilters['search'] = Str::squish($normalizedFilters['search']);
        }

        if ($legalEntityIds !== null) {
            $normalizedFilters['_legal_entity_ids'] = $legalEntityIds;
        }

        if ($locationIds !== null) {
            $normalizedFilters['_location_ids'] = $locationIds;
        }

        return $this->organizations->paginateLegalEntities(
            array_filter(
                $normalizedFilters,
                fn (mixed $value): bool => $value !== null && $value !== '',
            ),
            min(max($perPage, 1), 100),
        );
    }

    /** @return Collection<int, LegalEntity> */
    public function legalEntities(bool $onlyActive = false): Collection
    {
        return $this->organizations->legalEntities($onlyActive);
    }

    /** @return array{locations_total: int, locations_active: int, locations_preparation: int, locations_archived: int, legal_entities_total: int, legal_entities_active: int} */
    public function summaries(
        ?array $locationIds = null,
        ?array $legalEntityIds = null,
    ): array {
        return $this->organizations->summaries($locationIds, $legalEntityIds);
    }

    /** @param array<string, mixed> $data */
    public function createLegalEntity(
        array $data,
        User $actor,
        AuditContext $auditContext,
    ): LegalEntity {
        return DB::transaction(function () use ($data, $actor, $auditContext): LegalEntity {
            $legalEntity = $this->organizations->createLegalEntity(
                $this->legalEntityAttributes($data),
            );
            $this->billingSetup->initializeFor($legalEntity, [
                'arca_certificate' => $data['arca_certificate'] ?? null,
                'arca_private_key' => $data['arca_private_key'] ?? null,
            ]);
            $this->audit->record(
                event: 'legal_entity.created',
                auditable: $legalEntity,
                afterValues: $this->legalEntitySnapshot($legalEntity),
                actor: $actor,
                context: $auditContext,
            );

            return $legalEntity;
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function updateLegalEntity(
        LegalEntity $legalEntity,
        array $data,
        User $actor,
        AuditContext $auditContext,
    ): LegalEntity {
        return DB::transaction(function () use ($legalEntity, $data, $actor, $auditContext): LegalEntity {
            $lockedLegalEntity = $this->organizations->lockLegalEntityForUpdate($legalEntity);
            $this->ensureNotArchived($lockedLegalEntity);
            $attributes = $this->legalEntityAttributes($data);

            if ($attributes['status'] === OrganizationStatus::Inactive
                && $this->organizations->hasActiveLocations($lockedLegalEntity)) {
                throw ValidationException::withMessages([
                    'status' => 'Inactivá o archivá todas las sedes activas antes de inactivar la razón social.',
                ]);
            }

            $beforeValues = $this->legalEntitySnapshot($lockedLegalEntity);
            $statusChanged = $lockedLegalEntity->status !== $attributes['status'];
            $updatedLegalEntity = $this->organizations->updateLegalEntity(
                $lockedLegalEntity,
                $attributes,
            );
            $this->billingSetup->initializeFor($updatedLegalEntity, [
                'arca_certificate' => $data['arca_certificate'] ?? null,
                'arca_private_key' => $data['arca_private_key'] ?? null,
            ]);
            $afterValues = $this->legalEntitySnapshot($updatedLegalEntity);

            if ($statusChanged) {
                $beforeValues = Arr::except($beforeValues, ['status', 'archived_at']);
                $afterValues = Arr::except($afterValues, ['status', 'archived_at']);
            }
            $this->audit->record(
                event: 'legal_entity.updated',
                auditable: $updatedLegalEntity,
                beforeValues: $beforeValues,
                afterValues: $afterValues,
                actor: $actor,
                context: $auditContext,
            );

            return $updatedLegalEntity;
        }, 3);
    }

    public function archiveLegalEntity(
        LegalEntity $legalEntity,
        User $actor,
        AuditContext $auditContext,
    ): LegalEntity {
        return DB::transaction(function () use ($legalEntity, $actor, $auditContext): LegalEntity {
            $lockedLegalEntity = $this->organizations->lockLegalEntityForUpdate($legalEntity);
            $this->ensureNotArchived($lockedLegalEntity);

            if ($this->organizations->hasUnarchivedLocations($lockedLegalEntity)) {
                throw ValidationException::withMessages([
                    'status' => 'Archivá todas las sedes operativas antes de archivar la razón social.',
                ]);
            }

            $archivedLegalEntity = $this->organizations->setLegalEntityStatus(
                $lockedLegalEntity,
                OrganizationStatus::Archived,
                now(),
            );
            $this->audit->record(
                event: 'legal_entity.archived',
                auditable: $archivedLegalEntity,
                actor: $actor,
                context: $auditContext,
            );

            return $archivedLegalEntity;
        }, 3);
    }

    public function restoreLegalEntity(
        LegalEntity $legalEntity,
        User $actor,
        AuditContext $auditContext,
    ): LegalEntity {
        return DB::transaction(function () use ($legalEntity, $actor, $auditContext): LegalEntity {
            $lockedLegalEntity = $this->organizations->lockLegalEntityForUpdate($legalEntity);

            if (! $lockedLegalEntity->isArchived()) {
                throw ValidationException::withMessages([
                    'status' => 'Sólo se pueden restaurar razones sociales archivadas.',
                ]);
            }

            $restoredLegalEntity = $this->organizations->setLegalEntityStatus(
                $lockedLegalEntity,
                OrganizationStatus::Active,
                null,
            );
            $this->audit->record(
                event: 'legal_entity.restored',
                auditable: $restoredLegalEntity,
                actor: $actor,
                context: $auditContext,
            );

            return $restoredLegalEntity;
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function createLocation(
        array $data,
        User $actor,
        AuditContext $auditContext,
    ): Location {
        return DB::transaction(function () use ($data, $actor, $auditContext): Location {
            if (! $this->scope->canAccessLegalEntity($actor, (int) $data['legal_entity_id'])) {
                throw ValidationException::withMessages([
                    'legal_entity_id' => 'La razón social seleccionada está fuera de tu alcance.',
                ]);
            }
            $legalEntity = $this->organizations->findLegalEntityOrFail(
                (int) $data['legal_entity_id'],
            );
            $lockedLegalEntity = $this->organizations->lockLegalEntityForUpdate($legalEntity);
            $this->ensureLegalEntityIsActive($lockedLegalEntity);
            $location = $this->organizations->createLocation(
                $this->locationAttributes($data),
            );
            $this->audit->record(
                event: 'location.created',
                auditable: $location,
                afterValues: $this->locationSnapshot($location),
                actor: $actor,
                context: $auditContext,
            );

            return $location;
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function updateLocation(
        Location $location,
        array $data,
        User $actor,
        AuditContext $auditContext,
    ): Location {
        return DB::transaction(function () use ($location, $data, $actor, $auditContext): Location {
            if (! $this->scope->canAccessLegalEntity($actor, (int) $data['legal_entity_id'])) {
                throw ValidationException::withMessages([
                    'legal_entity_id' => 'La razón social seleccionada está fuera de tu alcance.',
                ]);
            }
            $legalEntity = $this->organizations->findLegalEntityOrFail(
                (int) $data['legal_entity_id'],
            );
            $lockedLegalEntity = $this->organizations->lockLegalEntityForUpdate($legalEntity);
            $this->ensureLegalEntityIsActive($lockedLegalEntity);
            $lockedLocation = $this->organizations->lockLocationForUpdate($location);
            $this->ensureNotArchived($lockedLocation);
            $beforeValues = $this->locationSnapshot($lockedLocation);
            $statusChanged = $lockedLocation->status !== OrganizationStatus::from($data['status']);
            $updatedLocation = $this->organizations->updateLocation(
                $lockedLocation,
                $this->locationAttributes($data),
            );
            $afterValues = $this->locationSnapshot($updatedLocation);

            if ($statusChanged) {
                $beforeValues = Arr::except($beforeValues, ['status', 'archived_at']);
                $afterValues = Arr::except($afterValues, ['status', 'archived_at']);
            }
            $this->audit->record(
                event: 'location.updated',
                auditable: $updatedLocation,
                beforeValues: $beforeValues,
                afterValues: $afterValues,
                actor: $actor,
                context: $auditContext,
            );

            return $updatedLocation;
        }, 3);
    }

    public function archiveLocation(
        Location $location,
        User $actor,
        AuditContext $auditContext,
    ): Location {
        return DB::transaction(function () use ($location, $actor, $auditContext): Location {
            $legalEntity = $this->organizations->findLegalEntityOrFail(
                (int) $location->legal_entity_id,
            );
            $this->organizations->lockLegalEntityForUpdate($legalEntity);
            $lockedLocation = $this->organizations->lockLocationForUpdate($location);
            $this->ensureNotArchived($lockedLocation);
            $archivedLocation = $this->organizations->setLocationStatus(
                $lockedLocation,
                OrganizationStatus::Archived,
                now(),
            );
            $this->audit->record(
                event: 'location.archived',
                auditable: $archivedLocation,
                actor: $actor,
                context: $auditContext,
            );

            return $archivedLocation;
        }, 3);
    }

    public function restoreLocation(
        Location $location,
        User $actor,
        AuditContext $auditContext,
    ): Location {
        return DB::transaction(function () use ($location, $actor, $auditContext): Location {
            $legalEntity = $this->organizations->findLegalEntityOrFail(
                (int) $location->legal_entity_id,
            );
            $lockedLegalEntity = $this->organizations->lockLegalEntityForUpdate(
                $legalEntity,
            );
            $lockedLocation = $this->organizations->lockLocationForUpdate($location);

            if (! $lockedLocation->isArchived()) {
                throw ValidationException::withMessages([
                    'status' => 'Sólo se pueden restaurar sedes archivadas.',
                ]);
            }

            $this->ensureLegalEntityIsActive($lockedLegalEntity);
            $restoredLocation = $this->organizations->setLocationStatus(
                $lockedLocation,
                OrganizationStatus::Active,
                null,
            );
            $this->audit->record(
                event: 'location.restored',
                auditable: $restoredLocation,
                actor: $actor,
                context: $auditContext,
            );

            return $restoredLocation;
        }, 3);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function legalEntityAttributes(array $data): array
    {
        $status = OrganizationStatus::from($data['status']);

        if ($status === OrganizationStatus::Archived
            || $status === OrganizationStatus::Preparation) {
            throw ValidationException::withMessages([
                'status' => 'El estado indicado no es válido para una razón social.',
            ]);
        }

        return [
            'business_name' => Str::squish($data['business_name']),
            'cuit' => (string) preg_replace('/\D+/', '', $data['cuit']),
            'tax_condition' => $data['tax_condition'],
            'fiscal_address' => Str::squish($data['fiscal_address']),
            'fiscal_point_of_sale' => $data['fiscal_point_of_sale'] ?? null,
            'arca_environment' => $data['arca_environment'] ?? null,
            'status' => $status,
            'archived_at' => null,
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function locationAttributes(array $data): array
    {
        $status = OrganizationStatus::from($data['status']);

        if ($status === OrganizationStatus::Archived) {
            throw ValidationException::withMessages([
                'status' => 'El archivado debe realizarse mediante la acción correspondiente.',
            ]);
        }

        return [
            'legal_entity_id' => (int) $data['legal_entity_id'],
            'name' => Str::squish($data['name']),
            'code' => Str::upper(Str::squish($data['code'])),
            'address' => Str::squish($data['address']),
            'neighborhood' => $this->nullableSquishedString($data['neighborhood'] ?? null),
            'postal_code' => $this->nullableTrimmedString($data['postal_code'] ?? null),
            'phone' => $this->nullableTrimmedString($data['phone'] ?? null),
            'email' => filled($data['email'] ?? null)
                ? Str::lower(trim($data['email']))
                : null,
            'opening_date' => $data['opening_date'] ?? null,
            'opening_time' => $data['opening_time'],
            'closing_time' => $data['closing_time'],
            'timezone' => trim($data['timezone']),
            'services' => array_values(array_unique($data['services'])),
            'status' => $status,
            'archived_at' => null,
        ];
    }

    private function ensureNotArchived(LegalEntity|Location $resource): void
    {
        if ($resource->isArchived()) {
            throw ValidationException::withMessages([
                'status' => 'El registro debe restaurarse antes de poder modificarlo.',
            ]);
        }
    }

    private function ensureLegalEntityIsActive(LegalEntity $legalEntity): void
    {
        if ($legalEntity->status !== OrganizationStatus::Active) {
            throw ValidationException::withMessages([
                'legal_entity_id' => 'La razón social elegida debe estar activa.',
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function legalEntitySnapshot(LegalEntity $legalEntity): array
    {
        return [
            'business_name' => $legalEntity->business_name,
            'cuit' => $legalEntity->cuit,
            'tax_condition' => $legalEntity->tax_condition,
            'fiscal_address' => $legalEntity->fiscal_address,
            'fiscal_point_of_sale' => $legalEntity->fiscal_point_of_sale,
            'arca_environment' => $legalEntity->arca_environment,
            'status' => $legalEntity->status->value,
            'archived_at' => $legalEntity->archived_at?->toISOString(),
        ];
    }

    /** @return array<string, mixed> */
    private function locationSnapshot(Location $location): array
    {
        return [
            'legal_entity_id' => $location->legal_entity_id,
            'name' => $location->name,
            'code' => $location->code,
            'address' => $location->address,
            'neighborhood' => $location->neighborhood,
            'postal_code' => $location->postal_code,
            'phone' => $location->phone,
            'email' => $location->email,
            'opening_date' => $location->opening_date?->toDateString(),
            'opening_time' => $location->opening_time,
            'closing_time' => $location->closing_time,
            'timezone' => $location->timezone,
            'services' => $location->services,
            'status' => $location->status->value,
            'archived_at' => $location->archived_at?->toISOString(),
        ];
    }

    private function nullableSquishedString(mixed $value): ?string
    {
        return filled($value) ? Str::squish((string) $value) : null;
    }

    private function nullableTrimmedString(mixed $value): ?string
    {
        return filled($value) ? trim((string) $value) : null;
    }
}
