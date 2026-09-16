<?php

namespace App\Domains\Users\Repositories\Eloquent;

use App\Domains\Users\Enums\RoleCode;
use App\Domains\Users\Enums\UserStatus;
use App\Domains\Users\Models\User;
use App\Domains\Users\Repositories\Contracts\UserRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class EloquentUserRepository implements UserRepository
{
    /**
     * @param  array{search?: string|null, role?: string|null, status?: string|null}  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(array $filters, int $perPage = 12): LengthAwarePaginator
    {
        return User::query()
            ->select([
                'id',
                'role_id',
                'name',
                'email',
                'status',
                'permissions',
                'last_login_at',
                'invitation_expires_at',
                'invitation_accepted_at',
                'created_at',
            ])
            ->with('role:id,code,name')
            ->when(
                $filters['search'] ?? null,
                fn (Builder $query, string $search): Builder => $query->where(
                    fn (Builder $searchQuery): Builder => $searchQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"),
                ),
            )
            ->when(
                $filters['role'] ?? null,
                fn (Builder $query, string $role): Builder => $query->whereHas(
                    'role',
                    fn (Builder $roleQuery): Builder => $roleQuery->where('code', $role),
                ),
            )
            ->when(
                $filters['status'] ?? null,
                fn (Builder $query, string $status): Builder => $query->where('status', $status),
            )
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function summaries(): array
    {
        return [
            'total' => User::query()->count(),
            'active' => User::query()->where('status', UserStatus::Active->value)->count(),
            'pending' => User::query()->where('status', UserStatus::PendingInvitation->value)->count(),
            'suspended' => User::query()->where('status', UserStatus::Suspended->value)->count(),
            'custom_permissions' => User::query()
                ->whereNotNull('permissions')
                ->where('permissions', '<>', '[]')
                ->count(),
        ];
    }

    public function create(array $attributes): User
    {
        return User::query()->create($attributes)->load('role');
    }

    public function update(User $user, array $attributes): User
    {
        $user->update($attributes);

        return $user->refresh()->load('role');
    }

    public function findForUpdate(int $userId): User
    {
        return User::query()
            ->lockForUpdate()
            ->findOrFail($userId);
    }

    public function lockActiveByRole(RoleCode $role): void
    {
        User::query()
            ->select('id')
            ->where('status', UserStatus::Active->value)
            ->whereHas(
                'role',
                fn (Builder $query): Builder => $query->where('code', $role->value),
            )
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    public function countActiveByRole(RoleCode $role, ?int $excludingUserId = null): int
    {
        return User::query()
            ->where('status', UserStatus::Active->value)
            ->whereHas(
                'role',
                fn (Builder $query): Builder => $query->where('code', $role->value),
            )
            ->when(
                $excludingUserId !== null,
                fn (Builder $query): Builder => $query->whereKeyNot($excludingUserId),
            )
            ->count();
    }
}
