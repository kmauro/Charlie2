<?php

namespace App\Domains\Users\Services;

use App\Domains\Users\Data\InvitationResult;
use App\Domains\Users\Enums\RoleCode;
use App\Domains\Users\Enums\UserStatus;
use App\Domains\Users\Mail\UserInvitationMail;
use App\Domains\Users\Models\User;
use App\Domains\Users\Repositories\Contracts\RoleRepository;
use App\Domains\Users\Repositories\Contracts\UserRepository;
use DateTimeInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function __construct(
        private UserRepository $users,
        private RoleRepository $roles,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function invite(array $data): InvitationResult
    {
        return $this->createInvitation($data, allowProfessionalRole: false);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function inviteProfessional(array $data): InvitationResult
    {
        return $this->createInvitation(
            [
                ...Arr::except($data, ['role_id', 'role_code']),
                'role_code' => RoleCode::Professional,
            ],
            allowProfessionalRole: true,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function invitePatient(array $data): InvitationResult
    {
        return $this->createInvitation([
            ...Arr::except($data, ['role_id', 'role_code']),
            'role_code' => RoleCode::Patient,
        ], allowProfessionalRole: false);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createInvitation(
        array $data,
        bool $allowProfessionalRole,
    ): InvitationResult {
        $role = array_key_exists('role_id', $data)
            ? $this->roles->find((int) $data['role_id'])
            : $this->roles->findByCode($this->roleCode($data['role_code'] ?? null));

        if ($role->code === RoleCode::Professional && ! $allowProfessionalRole) {
            throw ValidationException::withMessages([
                'role_id' => 'Los profesionales deben darse de alta desde el módulo Profesionales.',
            ]);
        }

        $expiresAt = now()->addHours((int) ($data['expires_in_hours'] ?? 48));
        $token = Str::random(64);

        $user = $this->users->create([
            'role_id' => $role->getKey(),
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Str::random(64),
            'status' => UserStatus::PendingInvitation,
            'permissions' => $data['permissions'] ?? [],
            'invitation_token_hash' => hash('sha256', $token),
            'invitation_expires_at' => $expiresAt,
        ]);

        return $this->sendInvitation($user, $token, $expiresAt);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): User
    {
        $attributes = Arr::only($data, ['name', 'email', 'role_id', 'permissions']);

        if (! array_key_exists('role_id', $attributes)) {
            return $this->users->update($user, $attributes);
        }

        return DB::transaction(function () use ($user, $attributes): User {
            $this->users->lockActiveByRole(RoleCode::SuperAdmin);
            $lockedUser = $this->users->findForUpdate((int) $user->getKey());
            $nextRole = $this->roles->find((int) $attributes['role_id']);

            $this->guardLastActiveSuperAdmin(
                user: $lockedUser,
                remainsSuperAdmin: $nextRole->code === RoleCode::SuperAdmin,
            );

            if (
                $lockedUser->hasRole(RoleCode::Professional)
                &&
                $nextRole->code !== RoleCode::Professional
            ) {
                throw ValidationException::withMessages([
                    'role_id' => 'El rol Profesional no puede cambiarse mientras exista un perfil profesional asociado.',
                ]);
            }

            if (
                ! $lockedUser->hasRole(RoleCode::Professional)
                &&
                $nextRole->code === RoleCode::Professional
            ) {
                throw ValidationException::withMessages([
                    'role_id' => 'Los profesionales deben darse de alta desde el módulo Profesionales.',
                ]);
            }

            return $this->users->update($lockedUser, $attributes);
        }, attempts: 3);
    }

    /**
     * @param  array{name: string, email: string}  $data
     */
    public function updatePatientIdentity(User $user, array $data): User
    {
        if (! $user->hasRole(RoleCode::Patient)) {
            throw ValidationException::withMessages([
                'email' => 'La cuenta vinculada no pertenece a un paciente.',
            ]);
        }

        return $this->users->update($user, Arr::only($data, ['name', 'email']));
    }

    public function suspend(User $user): User
    {
        if ($user->hasRole(RoleCode::Professional)) {
            throw ValidationException::withMessages([
                'user' => 'El estado de una cuenta profesional se gestiona desde el módulo Profesionales.',
            ]);
        }

        return $this->suspendAccount($user);
    }

    public function suspendProfessional(User $user): User
    {
        if (! $user->hasRole(RoleCode::Professional)) {
            throw ValidationException::withMessages([
                'user' => 'La cuenta seleccionada no pertenece a un profesional.',
            ]);
        }

        return $this->suspendAccount($user);
    }

    private function suspendAccount(User $user): User
    {
        if (Auth::id() === $user->getKey()) {
            throw ValidationException::withMessages([
                'user' => 'No podés suspender tu propia cuenta.',
            ]);
        }

        if ($user->status === UserStatus::Active && $user->hasRole(RoleCode::SuperAdmin)) {
            return DB::transaction(function () use ($user): User {
                $this->users->lockActiveByRole(RoleCode::SuperAdmin);
                $lockedUser = $this->users->findForUpdate((int) $user->getKey());

                $this->guardLastActiveSuperAdmin(
                    user: $lockedUser,
                    remainsSuperAdmin: false,
                );

                return $this->suspendUser($lockedUser);
            }, attempts: 3);
        }

        return $this->suspendUser($user);
    }

    private function suspendUser(User $user): User
    {
        return $this->users->update($user, [
            'status' => UserStatus::Suspended,
            'invitation_token_hash' => null,
            'invitation_expires_at' => null,
        ]);
    }

    public function restore(User $user): User
    {
        if ($user->hasRole(RoleCode::Professional)) {
            throw ValidationException::withMessages([
                'user' => 'El estado de una cuenta profesional se gestiona desde el módulo Profesionales.',
            ]);
        }

        return $this->restoreAccount($user);
    }

    public function restoreProfessional(User $user): User
    {
        if (! $user->hasRole(RoleCode::Professional)) {
            throw ValidationException::withMessages([
                'user' => 'La cuenta seleccionada no pertenece a un profesional.',
            ]);
        }

        return $this->restoreAccount($user);
    }

    private function restoreAccount(User $user): User
    {
        return $this->users->update($user, [
            'status' => $user->invitation_accepted_at === null
                ? UserStatus::PendingInvitation
                : UserStatus::Active,
        ]);
    }

    public function reinvite(User $user, int $expiresInHours = 48): InvitationResult
    {
        if ($user->invitation_accepted_at !== null) {
            throw ValidationException::withMessages([
                'user' => 'La cuenta ya fue activada y no requiere una nueva invitación.',
            ]);
        }

        if ($user->status === UserStatus::Suspended) {
            throw ValidationException::withMessages([
                'user' => 'Primero recuperá la cuenta suspendida.',
            ]);
        }

        $expiresAt = now()->addHours($expiresInHours);
        $token = Str::random(64);

        $user = $this->users->update($user, [
            'status' => UserStatus::PendingInvitation,
            'invitation_token_hash' => hash('sha256', $token),
            'invitation_expires_at' => $expiresAt,
        ]);

        return $this->sendInvitation($user, $token, $expiresAt);
    }

    /**
     * @param  array{name: string, password: string}  $data
     */
    public function acceptInvitation(User $user, string $token, array $data): User
    {
        return DB::transaction(function () use ($user, $token, $data): User {
            $lockedUser = $this->users->findForUpdate((int) $user->getKey());

            $this->ensureInvitationIsValid($lockedUser, $token);

            return $this->users->update($lockedUser, [
                'name' => $data['name'],
                'password' => $data['password'],
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
                'invitation_accepted_at' => now(),
                'invitation_token_hash' => null,
                'invitation_expires_at' => null,
            ]);
        }, attempts: 3);
    }

    public function ensureInvitationIsValid(User $user, string $token): void
    {
        $isValidToken = $user->invitation_token_hash !== null
            && hash_equals($user->invitation_token_hash, hash('sha256', $token));

        if (
            ! $isValidToken
            || $user->status !== UserStatus::PendingInvitation
            || $user->invitation_accepted_at !== null
            || $user->invitation_expires_at === null
            || $user->invitation_expires_at->isPast()
        ) {
            throw ValidationException::withMessages([
                'invitation' => 'La invitación es inválida, venció o ya fue utilizada.',
            ]);
        }
    }

    private function roleCode(mixed $value): RoleCode
    {
        if ($value instanceof RoleCode) {
            return $value;
        }

        if (is_string($value)) {
            return RoleCode::from($value);
        }

        throw ValidationException::withMessages([
            'role_id' => 'El rol seleccionado no es válido.',
        ]);
    }

    private function guardLastActiveSuperAdmin(User $user, bool $remainsSuperAdmin): void
    {
        if (
            $user->status !== UserStatus::Active
            || ! $user->hasRole(RoleCode::SuperAdmin)
            || $remainsSuperAdmin
        ) {
            return;
        }

        if ($this->users->countActiveByRole(RoleCode::SuperAdmin, $user->getKey()) === 0) {
            throw ValidationException::withMessages([
                'user' => 'Debe permanecer al menos un Superadministrador activo.',
            ]);
        }
    }

    private function invitationUrl(User $user, string $token, DateTimeInterface $expiresAt): string
    {
        return URL::temporarySignedRoute(
            'register.accept',
            $expiresAt,
            ['user' => $user->getRouteKey(), 'token' => $token],
        );
    }

    private function sendInvitation(
        User $user,
        string $token,
        DateTimeInterface $expiresAt,
    ): InvitationResult {
        $invitationUrl = $this->invitationUrl($user, $token, $expiresAt);

        Mail::to($user->email)->send(new UserInvitationMail(
            recipientName: $user->name,
            invitationUrl: $invitationUrl,
            expiresAt: $expiresAt,
        ));

        return new InvitationResult(
            user: $user,
            invitationUrl: $invitationUrl,
        );
    }
}
