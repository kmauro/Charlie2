<?php

namespace Database\Seeders;

use App\Domains\Users\Enums\RoleCode;
use App\Domains\Users\Enums\UserStatus;
use App\Domains\Users\Models\Role;
use App\Domains\Users\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $name = trim((string) config('his.superadmin.name'));
        $email = Str::lower(trim((string) config('his.superadmin.email')));
        $password = (string) config('his.superadmin.password');

        $validator = Validator::make(
            compact('name', 'email', 'password'),
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255'],
                'password' => [
                    'required',
                    Password::min(12)
                        ->letters()
                        ->mixedCase()
                        ->numbers()
                        ->symbols(),
                ],
            ],
        );

        if ($validator->fails()) {
            throw new RuntimeException(
                'Las credenciales configuradas para el Superadmin no son válidas: '
                .$validator->errors()->first(),
            );
        }

        $role = Role::query()
            ->where('code', RoleCode::SuperAdmin->value)
            ->firstOrFail();

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'role_id' => $role->getKey(),
                'name' => $name,
                'password' => $password,
                'status' => UserStatus::Active,
                'permissions' => [],
                'email_verified_at' => now(),
                'invitation_accepted_at' => now(),
                'invitation_token_hash' => null,
                'invitation_expires_at' => null,
            ],
        );
    }
}
