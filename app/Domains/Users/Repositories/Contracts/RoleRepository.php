<?php

namespace App\Domains\Users\Repositories\Contracts;

use App\Domains\Users\Enums\RoleCode;
use App\Domains\Users\Models\Role;
use Illuminate\Support\Collection;

interface RoleRepository
{
    /**
     * @return Collection<int, Role>
     */
    public function all(): Collection;

    public function find(int $id): Role;

    public function findByCode(RoleCode $code): Role;
}
