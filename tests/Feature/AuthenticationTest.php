<?php

use App\Domains\Users\Enums\UserStatus;
use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

it('shows the login and invitation-only registration screens to guests', function () {
    $this->get(route('login'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/views/Login'));

    $this->get(route('register'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/views/Register')
            ->where('invitation', null)
            ->where('acceptUrl', null));
});

it('authenticates an active user with a role and records the last login', function () {
    $password = 'A-secure-login-password!23';
    $user = User::factory()->create([
        'password' => Hash::make($password),
        'last_login_at' => null,
    ]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => $password,
        'remember' => true,
    ])->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
    expect($user->refresh()->last_login_at)->not->toBeNull();
});

it('rejects invalid credentials without authenticating a user', function () {
    $user = User::factory()->create([
        'password' => Hash::make('A-valid-password!23'),
    ]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'An-invalid-password!23',
    ])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
    expect($user->refresh()->last_login_at)->toBeNull();
});

it('rejects a suspended account even when its credentials are valid', function () {
    $password = 'A-valid-password!23';
    $user = User::factory()->suspended()->create([
        'password' => Hash::make($password),
        'status' => UserStatus::Suspended,
    ]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => $password,
    ])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
    expect($user->refresh()->last_login_at)->toBeNull();
});

it('invalidates an existing session after the account is suspended', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $user->update(['status' => UserStatus::Suspended]);

    $this->get(route('dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('redirects guests away from authenticated routes', function (string $routeName) {
    $this->get(route($routeName))->assertRedirect(route('login'));
})->with([
    'dashboard',
    'users.index',
    'professionals.index',
]);

it('redirects authenticated users away from guest routes', function (string $routeName) {
    $this->actingAs(User::factory()->create());

    $this->get(route($routeName))->assertRedirect('/dashboard');
})->with([
    'login',
    'register',
]);

it('logs out and invalidates the authenticated session', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('logout'))->assertRedirect(route('login'));

    $this->assertGuest();
});
