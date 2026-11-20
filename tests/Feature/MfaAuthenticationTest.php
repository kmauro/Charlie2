<?php

use App\Domains\Auth\Models\MfaRecoveryCode;
use App\Domains\Auth\Models\MfaTrustedDevice;
use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\Hash;

test('a user without mfa can defer setup after correct credentials', function (): void {
    $user = User::factory()->superAdmin()->create(['password' => 'password']);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
        'remember' => true,
    ])->assertRedirect(route('account.security.prompt'));

    $this->assertAuthenticatedAs($user);
    $response->assertSessionHas('mfa.setup_prompt', true);
});

test('an mfa required role cannot use an existing session without verification', function (): void {
    config(['mfa.required_roles' => ['superadmin']]);
    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('a recovery code can only be used once', function (): void {
    $user = User::factory()->superAdmin()->create();
    $user->mfaRecoveryCodes()->create(['code_hash' => Hash::make('ABCD-EFGH')]);
    session(['mfa.challenge' => [
        'user_id' => $user->getKey(),
        'remember' => false,
        'attempts' => 0,
        'expires_at' => now()->addMinutes(5)->getTimestamp(),
    ]]);

    $this->post(route('mfa.challenge.recovery'), ['recovery_code' => 'ABCD-EFGH'])
        ->assertRedirect(route('dashboard'));

    expect(MfaRecoveryCode::query()->sole()->used_at)->not->toBeNull();
});

test('a trusted device bypasses a configured totp challenge', function (): void {
    $user = User::factory()->superAdmin()->create(['password' => 'password']);
    $user->mfaFactor()->create([
        'secret' => 'ABCDEFGHIJKLMNOPQRST',
        'confirmed_at' => now(),
    ]);
    $token = 'trusted-device-token';
    MfaTrustedDevice::query()->create([
        'user_id' => $user->getKey(),
        'token_hash' => hash('sha256', $token),
        'expires_at' => now()->addDays(30),
    ]);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('mfa.challenge.create'));

    $this->withCookie((string) config('mfa.trusted_device_cookie'), $token)
        ->get(route('mfa.challenge.create'))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});
