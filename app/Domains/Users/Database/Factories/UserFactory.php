<?php

namespace App\Domains\Users\Database\Factories;

use App\Domains\Users\Enums\RoleCode;
use App\Domains\Users\Enums\UserPermission;
use App\Domains\Users\Enums\UserStatus;
use App\Domains\Users\Models\Role;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'role_id' => fn (): int => Role::query()->firstOrCreate(
                ['code' => RoleCode::Reception->value],
                ['name' => RoleCode::Reception->label()],
            )->getKey(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'status' => UserStatus::Active,
            'permissions' => [UserPermission::ManageAppointments->value],
            'remember_token' => Str::random(10),
            'invitation_accepted_at' => now(),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }

    public function pendingInvitation(): static
    {
        return $this->state(function (array $attributes): array {
            $token = Str::random(64);

            return [
                'email_verified_at' => null,
                'status' => UserStatus::PendingInvitation,
                'invitation_token_hash' => hash('sha256', $token),
                'invitation_expires_at' => now()->addHours(48),
                'invitation_accepted_at' => null,
            ];
        });
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => UserStatus::Suspended,
        ]);
    }

    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role_id' => Role::query()->firstOrCreate(
                ['code' => RoleCode::SuperAdmin->value],
                ['name' => RoleCode::SuperAdmin->label()],
            )->getKey(),
        ]);
    }
}
