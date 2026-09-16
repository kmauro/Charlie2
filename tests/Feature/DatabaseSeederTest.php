<?php

use App\Domains\Users\Database\Seeders\RoleSeeder;
use App\Domains\Users\Database\Seeders\SuperAdminSeeder;
use App\Domains\Users\Enums\RoleCode;
use App\Domains\Users\Enums\UserStatus;
use App\Domains\Users\Models\Role;
use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\Hash;

it('uses the domain user model and its domain factory for authentication', function () {
    $user = User::factory()->create();

    expect($user)
        ->toBeInstanceOf(User::class)
        ->and(config('auth.providers.users.model'))->toBe(User::class)
        ->and(file_exists(app_path('Models/User.php')))->toBeFalse();
});

it('seeds only one idempotent superadmin and no legacy sample user', function () {
    $password = 'Seeder-password!2026';

    config()->set('his.superadmin', [
        'name' => 'Administración General',
        'email' => 'superadmin@clinica.test',
        'password' => $password,
    ]);

    $this->seed();
    $this->seed();

    expect(Role::query()->count())
        ->toBe(count(RoleCode::cases()))
        ->and(User::query()->count())->toBe(1)
        ->and(User::query()->where('email', 'superadmin@clinica.test')->count())->toBe(1)
        ->and(User::query()->where('email', 'test@example.com')->exists())->toBeFalse();

    $superAdmin = User::query()
        ->with('role')
        ->where('email', 'superadmin@clinica.test')
        ->sole();

    expect($superAdmin->name)
        ->toBe('Administración General')
        ->and($superAdmin->status)->toBe(UserStatus::Active)
        ->and($superAdmin->role?->code)->toBe(RoleCode::SuperAdmin)
        ->and($superAdmin->email_verified_at)->not->toBeNull()
        ->and(Hash::check($password, $superAdmin->password))->toBeTrue();
});

it('rejects invalid superadmin credentials before persisting the account', function (array $credentials) {
    $this->seed(RoleSeeder::class);
    config()->set('his.superadmin', $credentials);

    expect(fn () => $this->seed(SuperAdminSeeder::class))
        ->toThrow(RuntimeException::class);

    expect(User::query()->count())->toBe(0);
})->with([
    'malformed email' => [[
        'name' => 'Administración General',
        'email' => 'correo-invalido',
        'password' => 'Strong-password!2026',
    ]],
    'weak password' => [[
        'name' => 'Administración General',
        'email' => 'superadmin@clinica.test',
        'password' => 'password',
    ]],
]);
