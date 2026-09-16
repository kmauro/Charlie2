<?php

namespace App\Domains\Users\Repositories\Contracts;

use App\Domains\Users\Enums\RoleCode;
use App\Domains\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface UserRepository
{
    /**
     * @param  array{search?: string|null, role?: string|null, status?: string|null}  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(array $filters, int $perPage = 12): LengthAwarePaginator;

    /**
     * @return array{total: int, active: int, pending: int, suspended: int, custom_permissions: int}
     */
    public function summaries(): array;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): User;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(User $user, array $attributes): User;

    public function findForUpdate(int $userId): User;

    public function lockActiveByRole(RoleCode $role): void;

    public function countActiveByRole(RoleCode $role, ?int $excludingUserId = null): int;
}
