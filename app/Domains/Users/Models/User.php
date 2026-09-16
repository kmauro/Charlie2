<?php

namespace App\Domains\Users\Models;

use App\Domains\Audits\Models\AuditLog;
use App\Domains\Auth\Notifications\ResetPasswordNotification;
use App\Domains\Organizations\Models\LegalEntity;
use App\Domains\Organizations\Models\Location;
use App\Domains\Patients\Models\Patient;
use App\Domains\Users\Database\Factories\UserFactory;
use App\Domains\Users\Enums\RoleCode;
use App\Domains\Users\Enums\UserPermission;
use App\Domains\Users\Enums\UserStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'role_id',
    'name',
    'email',
    'email_verified_at',
    'password',
    'status',
    'permissions',
    'last_login_at',
    'invitation_token_hash',
    'invitation_expires_at',
    'invitation_accepted_at',
])]
#[Hidden(['password', 'remember_token', 'invitation_token_hash'])]
#[UseFactory(UserFactory::class)]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $attributes = [
        'status' => UserStatus::Active->value,
    ];

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return HasOne<Patient, $this>
     */
    public function patient(): HasOne
    {
        return $this->hasOne(Patient::class);
    }

    /** @return BelongsToMany<Location, $this> */
    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class)->withTimestamps();
    }

    /** @return BelongsToMany<LegalEntity, $this> */
    public function legalEntities(): BelongsToMany
    {
        return $this->belongsToMany(LegalEntity::class)->withTimestamps();
    }

    /** @return HasMany<AuditLog, $this> */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_id');
    }

    public function hasRole(RoleCode $role): bool
    {
        return $this->role?->code === $role;
    }

    public function hasPermission(UserPermission $permission): bool
    {
        if ($this->hasRole(RoleCode::SuperAdmin)) {
            return true;
        }

        $role = $this->role?->code;

        return $role !== null
            && $permission->isAllowedFor($role)
            && in_array($permission->value, $this->permissions ?? [], true);
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function canAccessApplication(): bool
    {
        if (! $this->isActive() || $this->role === null) {
            return false;
        }

        if (! $this->hasRole(RoleCode::Patient)) {
            return true;
        }

        return $this->patient?->canAccessPortal() ?? false;
    }

    public function canResetPassword(): bool
    {
        return $this->invitation_accepted_at !== null
            && $this->canAccessApplication();
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification((string) $token));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'permissions' => 'array',
            'last_login_at' => 'datetime',
            'invitation_expires_at' => 'datetime',
            'invitation_accepted_at' => 'datetime',
        ];
    }
}
