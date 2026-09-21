<?php

use App\Domains\Auth\Notifications\ResetPasswordNotification;
use App\Domains\Patients\Enums\PatientStatus;
use App\Domains\Patients\Models\Patient;
use App\Domains\Users\Enums\RoleCode;
use App\Domains\Users\Enums\UserStatus;
use App\Domains\Users\Models\Role;
use App\Domains\Users\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function patientPortalUser(
    PatientStatus $status = PatientStatus::Active,
    ?DateTimeInterface $portalAccessUntil = null,
): User {
    $user = User::factory()->create([
        'role_id' => Role::query()->where('code', RoleCode::Patient->value)->value('id'),
    ]);
    Patient::factory()->for($user)->create([
        'status' => $status,
        'portal_access_until' => $portalAccessUntil,
    ]);

    return $user;
}

it('renders the password recovery screens', function () {
    $this->get(route('password.request'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->component('auth/views/ForgotPassword'));

    $this->get(route('password.reset', ['token' => 'test-token', 'email' => 'user@example.test']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/views/ResetPassword')
            ->where('token', 'test-token')
            ->where('email', 'user@example.test'));
});

it('sends a reset link only to accounts that can currently access the application', function () {
    Notification::fake();
    $activeUser = User::factory()->create();
    $gracePeriodPatient = patientPortalUser(
        PatientStatus::Deceased,
        now()->addMonth(),
    );
    $expiredPatient = patientPortalUser(PatientStatus::Blocked, now()->subSecond());

    $this->post(route('password.email'), ['email' => $activeUser->email])
        ->assertSessionHas('status');
    $this->post(route('password.email'), ['email' => $expiredPatient->email])
        ->assertSessionHas('status');
    $this->post(route('password.email'), ['email' => $gracePeriodPatient->email])
        ->assertSessionHas('status');
    $this->post(route('password.email'), ['email' => 'unknown@example.test'])
        ->assertSessionHas('status');

    Notification::assertSentTo($activeUser, ResetPasswordNotification::class);
    Notification::assertSentTo($gracePeriodPatient, ResetPasswordNotification::class);
    Notification::assertNotSentTo($expiredPatient, ResetPasswordNotification::class);
});

it('allows an enabled account to reset its password with the emailed token', function () {
    Notification::fake();
    $user = User::factory()->create();
    $token = null;

    $this->post(route('password.email'), ['email' => $user->email]);
    Notification::assertSentTo(
        $user,
        ResetPasswordNotification::class,
        function (ResetPasswordNotification $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        },
    );

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'Nueva-clave-segura-2026',
        'password_confirmation' => 'Nueva-clave-segura-2026',
    ])->assertRedirect(route('login'));

    expect(Hash::check('Nueva-clave-segura-2026', $user->fresh()->password))->toBeTrue();
});

it('keeps blocked archived and deceased patients enabled for two months only', function (PatientStatus $status) {
    $user = patientPortalUser($status, now()->addMonthsNoOverflow(2));

    $this->actingAs($user)->get(route('dashboard'))->assertSuccessful();

    $this->travel(2)->months();
    $this->travel(1)->seconds();

    $this->get(route('dashboard'))->assertRedirect(route('login'));
    expect($user->fresh()->status)->toBe(UserStatus::Active);
})->with([
    'blocked' => PatientStatus::Blocked,
    'archived' => PatientStatus::Archived,
    'deceased' => PatientStatus::Deceased,
]);

it('does not send reset links to pending or suspended accounts', function (string $state) {
    Notification::fake();
    $user = $state === 'pending'
        ? User::factory()->pendingInvitation()->create()
        : User::factory()->suspended()->create();

    $this->post(route('password.email'), ['email' => $user->email])
        ->assertSessionHas('status');

    Notification::assertNotSentTo($user, ResetPasswordNotification::class);
})->with(['pending', 'suspended']);
