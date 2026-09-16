<?php

namespace App\Domains\Users\Database\Factories;

use App\Domains\Users\Enums\RoleCode;
use App\Domains\Users\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $role = fake()->unique()->randomElement(RoleCode::cases());

        return [
            'code' => $role,
            'name' => $role->label(),
        ];
    }
}
