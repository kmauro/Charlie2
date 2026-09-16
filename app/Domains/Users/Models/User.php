<?php

namespace App\Domains\Users\Models;

use App\Domains\Patients\Models\Patient;
use App\Domains\Users\Database\Factories\UserFactory;
use App\Domains\Users\Enums\RoleCode;
use App\Domains\Users\Enums\UserStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    public function hasRole(RoleCode $role): bool
    {
        return $this->role?->code === $role;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
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
