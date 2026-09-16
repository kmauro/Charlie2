<?php

namespace App\Domains\Patients\Models;

use App\Domains\Patients\Database\Factories\PatientFactory;
use App\Domains\Patients\Enums\PatientStatus;
use App\Domains\Patients\Enums\RegisteredSex;
use App\Domains\Patients\Policies\PatientPolicy;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[UsePolicy(PatientPolicy::class)]
class Patient extends Model
{
    /** @use HasFactory<PatientFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'document_number',
        'first_name',
        'last_name',
        'birth_date',
        'registered_sex',
        'gender',
        'phone',
        'email',
        'address',
        'coverage_name',
        'member_number',
        'status',
        'lock_version',
        'previous_status',
        'archived_at',
    ];

    protected $attributes = [
        'status' => PatientStatus::Active->value,
        'lock_version' => 1,
    ];

    /**
     * @return HasMany<PatientContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(PatientContact::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', PatientStatus::Active->value);
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('status', PatientStatus::Archived->value);
    }

    public function isArchived(): bool
    {
        return $this->status === PatientStatus::Archived;
    }

    protected static function newFactory(): PatientFactory
    {
        return PatientFactory::new();
    }

    protected static function booted(): void
    {
        static::deleting(function (): never {
            throw new LogicException(
                'Los pacientes no pueden eliminarse físicamente; deben archivarse.',
            );
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'registered_sex' => RegisteredSex::class,
            'status' => PatientStatus::class,
            'lock_version' => 'integer',
            'previous_status' => PatientStatus::class,
            'archived_at' => 'datetime',
        ];
    }
}
