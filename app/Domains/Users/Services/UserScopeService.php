<?php

namespace App\Domains\Users\Services;

use App\Domains\Organizations\Models\Location;
use App\Domains\Professionals\Models\ProfessionalSchedule;
use App\Domains\Users\Enums\RoleCode;
use App\Domains\Users\Models\User;

class UserScopeService
{
    /** @return array<int, int>|null */
    public function locationIdsFor(User $user): ?array
    {
        if ($user->hasRole(RoleCode::SuperAdmin)) {
            return null;
        }

        if ($user->hasRole(RoleCode::Professional)) {
            return ProfessionalSchedule::query()
                ->whereHas('professional', fn ($query) => $query
                    ->where('user_id', $user->getKey()))
                ->whereNotNull('location_id')
                ->distinct()
                ->pluck('location_id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();
        }

        $user->loadMissing(['locations:id', 'legalEntities:id']);
        $directLocationIds = $user->locations->modelKeys();
        $legalEntityLocationIds = Location::query()
            ->whereIn('legal_entity_id', $user->legalEntities->modelKeys())
            ->pluck('id')
            ->all();

        return array_values(array_unique(array_map(
            fn (mixed $id): int => (int) $id,
            [...$directLocationIds, ...$legalEntityLocationIds],
        )));
    }

    public function canAccessLocation(User $user, int $locationId): bool
    {
        $locationIds = $this->locationIdsFor($user);

        return $locationIds === null || in_array($locationId, $locationIds, true);
    }

    public function canAccessLegalEntity(User $user, int $legalEntityId): bool
    {
        if ($user->hasRole(RoleCode::SuperAdmin)) {
            return true;
        }

        return in_array($legalEntityId, $this->legalEntityIdsFor($user) ?? [], true);
    }

    /** @return array<int, int>|null */
    public function legalEntityIdsFor(User $user): ?array
    {
        if ($user->hasRole(RoleCode::SuperAdmin)) {
            return null;
        }

        $user->loadMissing(['legalEntities:id', 'locations:id,legal_entity_id']);

        return array_values(array_unique(array_map(
            fn (mixed $id): int => (int) $id,
            [
                ...$user->legalEntities->modelKeys(),
                ...$user->locations->pluck('legal_entity_id')->all(),
            ],
        )));
    }
}
