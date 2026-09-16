<?php

namespace App\Domains\Users\Repositories\Eloquent;

use App\Domains\Users\Enums\RoleCode;
use App\Domains\Users\Models\Role;
use App\Domains\Users\Repositories\Contracts\RoleRepository;
use Illuminate\Support\Collection;

class EloquentRoleRepository implements RoleRepository
{
    public function all(): Collection
    {
        return Role::query()
            ->select(['id', 'code', 'name'])
            ->orderBy('id')
            ->get();
    }

    public function find(int $id): Role
    {
        return Role::query()->findOrFail($id);
    }

    public function findByCode(RoleCode $code): Role
    {
        return Role::query()
            ->where('code', $code->value)
            ->firstOrFail();
    }
}
