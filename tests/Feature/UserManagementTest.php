<?php

use App\Domains\Professionals\Enums\ProfessionalStatus;
use App\Domains\Professionals\Models\Professional;
use App\Domains\Users\Enums\RoleCode;
use App\Domains\Users\Enums\UserPermission;
use App\Domains\Users\Enums\UserStatus;
use App\Domains\Users\Mail\UserInvitationMail;
use App\Domains\Users\Models\Role;
use App\Domains\Users\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function usersSuperAdmin(array $attributes = []): User
{
    return User::factory()->superAdmin()->create($attributes);
}

function usersRole(RoleCode $code): Role
{
    return Role::query()->where('code', $code->value)->sole();
}

/**
 * @return array<string, mixed>
 */
function validUserInvitationPayload(array $overrides = []): array
{
    return [
        'name' => 'María Administrativa',
        'email' => 'maria.administrativa@clinica.test',
        'role_id' => usersRole(RoleCode::Reception)->getKey(),
        'expires_in_hours' => 48,
        'permissions' => [
            UserPermission::ManageAppointments->value,
            UserPermission::RecordPayments->value,
        ],
        ...$overrides,
    ];
}

it('allows only superadmins to access every user management action', function () {
    $actor = User::factory()->create();
    $target = User::factory()->create();

    $this->actingAs($actor);

    $this->get(route('users.index'))->assertForbidden();
    $this->get(route('users.create'))->assertForbidden();
    $this->post(route('users.store'), [])->assertForbidden();
    $this->get(route('users.edit', $target))->assertForbidden();
    $this->put(route('users.update', $target), [])->assertForbidden();
    $this->delete(route('users.destroy', $target))->assertForbidden();
    $this->patch(route('users.restore', $target))->assertForbidden();
    $this->post(route('users.reinvite', $target))->assertForbidden();
});

it('renders the user index with its paginator filters roles and summaries', function () {
    $actor = usersSuperAdmin();
    $actor->update(['name' => 'Super Admin']);
    $target = User::factory()->create([
        'name' => 'Ada Lovelace',
        'status' => UserStatus::Active,
    ]);

    $this->actingAs($actor)
        ->get(route('users.index', ['search' => 'Ada']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('users/views/Index')
            ->has('users.data', 1, fn (Assert $user) => $user
                ->where('id', $target->getKey())
                ->where('name', 'Ada Lovelace')
                ->has('role')
                ->has('status')
                ->has('permissions')
                ->etc())
            ->where('filters.search', 'Ada')
            ->has('roles', count(RoleCode::cases()))
            ->has('summaries', fn (Assert $summaries) => $summaries
                ->where('total', 2)
                ->where('active', 2)
                ->where('pending', 0)
                ->where('suspended', 0)
                ->has('custom_permissions')
                ->etc()));
});

it('renders the user create and edit screens with their form props', function () {
    $actor = usersSuperAdmin();
    $target = User::factory()->create([
        'permissions' => [UserPermission::ManageAppointments->value],
    ]);

    $this->actingAs($actor)
        ->get(route('users.create'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('users/views/Create')
            ->has('roles', count(RoleCode::cases()) - 1)
            ->has('permissionOptions', count(UserPermission::cases())));

    $this->get(route('users.edit', $target))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('users/views/Edit')
            ->where('user.id', $target->getKey())
            ->where('user.email', $target->email)
            ->has('roles', count(RoleCode::cases()) - 1)
            ->has('permissionOptions', count(UserPermission::cases())));
});

it('keeps the professional role fixed when editing a linked professional account', function () {
    $actor = usersSuperAdmin();
    $professional = Professional::factory()->create();

    $this->actingAs($actor)
        ->get(route('users.edit', $professional->user))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('users/views/Edit')
            ->has('roles', 1)
            ->where('roles.0.code', RoleCode::Professional->value));
});

it('requires professional accounts to be created from the professionals module', function () {
    $actor = usersSuperAdmin();
    $professionalRole = usersRole(RoleCode::Professional);

    $this->actingAs($actor)
        ->post(route('users.store'), validUserInvitationPayload([
            'email' => 'rol.profesional@clinica.test',
            'role_id' => $professionalRole->getKey(),
        ]))
        ->assertSessionHasErrors('role_id');

    $this->assertDatabaseMissing('users', [
        'email' => 'rol.profesional@clinica.test',
    ]);

    $target = User::factory()->create();

    $this->put(route('users.update', $target), [
        'name' => $target->name,
        'email' => $target->email,
        'role_id' => $professionalRole->getKey(),
        'permissions' => [],
    ])->assertSessionHasErrors('role_id');

    expect($target->refresh()->role?->code)->not->toBe(RoleCode::Professional);
});

it('invites a user through a temporary signed URL that can be accepted only once', function () {
    Mail::fake();
    $actor = usersSuperAdmin();

    $response = $this->actingAs($actor)
        ->post(route('users.store'), validUserInvitationPayload())
        ->assertRedirect(route('users.index'))
        ->assertInertiaFlash('invitationUrl');

    $invited = User::query()
        ->where('email', 'maria.administrativa@clinica.test')
        ->sole();
    $invitationUrl = $response->getSession()->get(SessionKey::FLASH_DATA)['invitationUrl'];

    Mail::assertQueued(
        UserInvitationMail::class,
        fn (UserInvitationMail $mail): bool => $mail->hasTo($invited->email)
            && $mail->invitationUrl === $invitationUrl,
    );

    expect($invitationUrl)
        ->toBeString()
        ->and($invited->status)->toBe(UserStatus::PendingInvitation)
        ->and($invited->invitation_token_hash)->not->toBeNull()
        ->and($invited->invitation_expires_at)->not->toBeNull()
        ->and($invited->invitation_accepted_at)->toBeNull();

    $this->post(route('logout'))->assertRedirect(route('login'));

    $this->get($invitationUrl)
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/views/Register')
            ->where('invitation.id', $invited->getKey())
            ->where('invitation.email', $invited->email)
            ->where('invitation.role.code', RoleCode::Reception->value)
            ->where('acceptUrl', function (string $acceptUrl) use ($invitationUrl): bool {
                parse_str((string) parse_url($acceptUrl, PHP_URL_QUERY), $acceptQuery);
                parse_str((string) parse_url($invitationUrl, PHP_URL_QUERY), $invitationQuery);
                ksort($acceptQuery);
                ksort($invitationQuery);

                return parse_url($acceptUrl, PHP_URL_PATH) === parse_url($invitationUrl, PHP_URL_PATH)
                    && $acceptQuery === $invitationQuery;
            }));

    $password = 'Invitation-password!2026';

    $this->post($invitationUrl, [
        'name' => 'María Administrativa Actualizada',
        'password' => $password,
        'password_confirmation' => $password,
    ])->assertRedirect(route('dashboard'));

    $invited->refresh();

    $this->assertAuthenticatedAs($invited);
    expect($invited->status)
        ->toBe(UserStatus::Active)
        ->and($invited->name)->toBe('María Administrativa Actualizada')
        ->and($invited->email_verified_at)->not->toBeNull()
        ->and($invited->invitation_accepted_at)->not->toBeNull()
        ->and($invited->invitation_token_hash)->toBeNull()
        ->and($invited->invitation_expires_at)->toBeNull()
        ->and(Hash::check($password, $invited->password))->toBeTrue();

    $this->post(route('logout'))->assertRedirect(route('login'));

    $this->from(route('login'))
        ->get($invitationUrl)
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('invitation');
});

it('rejects invitation acceptance without a valid URL signature', function () {
    $invited = User::factory()->pendingInvitation()->create();

    $this->get(route('register.accept', [
        'user' => $invited,
        'token' => 'unsigned-token',
    ]))->assertForbidden();
});

it('edits suspends and restores a user without deleting their record', function () {
    $actor = usersSuperAdmin();
    $target = User::factory()->create([
        'name' => 'Nombre anterior',
        'email' => 'anterior@clinica.test',
    ]);
    $managerRole = usersRole(RoleCode::Manager);

    $this->actingAs($actor)
        ->put(route('users.update', $target), [
            'name' => '  Nombre actualizado  ',
            'email' => 'ACTUALIZADO@CLINICA.TEST',
            'role_id' => $managerRole->getKey(),
            'permissions' => [UserPermission::ViewAccounting->value],
        ])
        ->assertRedirect(route('users.index'))
        ->assertInertiaFlash('success', 'El usuario fue actualizado correctamente.');

    $target->refresh();

    expect($target->name)
        ->toBe('Nombre actualizado')
        ->and($target->email)->toBe('actualizado@clinica.test')
        ->and($target->role_id)->toBe($managerRole->getKey())
        ->and($target->permissions)->toBe([UserPermission::ViewAccounting->value]);

    $this->delete(route('users.destroy', $target))
        ->assertRedirect()
        ->assertInertiaFlash('success');

    expect($target->refresh()->status)
        ->toBe(UserStatus::Suspended)
        ->and(User::query()->whereKey($target->getKey())->exists())->toBeTrue();

    $this->patch(route('users.restore', $target))
        ->assertRedirect()
        ->assertInertiaFlash('success');

    expect($target->refresh()->status)->toBe(UserStatus::Active);
});

it('updates a user when a single permission is submitted as a scalar value', function () {
    $actor = usersSuperAdmin();
    $target = User::factory()->create();
    $managerRole = usersRole(RoleCode::Manager);

    $this->actingAs($actor)
        ->put(route('users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'role_id' => $managerRole->getKey(),
            'permissions' => UserPermission::ViewAccounting->value,
        ])
        ->assertRedirect(route('users.index'));

    expect($target->refresh()->permissions)->toBe([
        UserPermission::ViewAccounting->value,
    ]);
});

it('requires professional account state to be managed from the professionals module', function () {
    $actor = usersSuperAdmin();
    $professional = Professional::factory()->create();

    $this->actingAs($actor)
        ->delete(route('users.destroy', $professional->user))
        ->assertSessionHasErrors('user');

    $professional->refresh()->load('user');

    expect($professional->status)
        ->toBe(ProfessionalStatus::Active)
        ->and($professional->user->status)->toBe(UserStatus::Active);

    $this->delete(route('professionals.destroy', $professional))
        ->assertRedirect()
        ->assertInertiaFlash('success');

    $this->patch(route('users.restore', $professional->user))
        ->assertSessionHasErrors('user');

    $professional->refresh()->load('user');

    expect($professional->status)
        ->toBe(ProfessionalStatus::Inactive)
        ->and($professional->user->status)->toBe(UserStatus::Suspended);
});

it('prevents a superadmin from suspending their own account', function () {
    $actor = usersSuperAdmin();

    $this->actingAs($actor)
        ->delete(route('users.destroy', $actor))
        ->assertSessionHasErrors('user');

    expect($actor->refresh()->status)->toBe(UserStatus::Active);
});

it('prevents demoting the last active superadmin', function () {
    $actor = usersSuperAdmin();

    $this->actingAs($actor)
        ->put(route('users.update', $actor), [
            'name' => $actor->name,
            'email' => $actor->email,
            'role_id' => usersRole(RoleCode::Administrator)->getKey(),
            'permissions' => [],
        ])
        ->assertSessionHasErrors('user');

    expect($actor->refresh()->role?->code)->toBe(RoleCode::SuperAdmin);
});
