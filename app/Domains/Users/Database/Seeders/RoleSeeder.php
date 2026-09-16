<?php

namespace App\Domains\Users\Database\Seeders;

use App\Domains\Users\Enums\RoleCode;
use App\Domains\Users\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (RoleCode::cases() as $role) {
            Role::query()->updateOrCreate(
                ['code' => $role->value],
                ['name' => $role->label()],
            );
        }
    }
}
