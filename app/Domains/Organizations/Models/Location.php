<?php

namespace App\Domains\Organizations\Models;

use App\Domains\Organizations\Enums\OrganizationStatus;
use App\Domains\Organizations\Policies\LocationPolicy;
use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[UsePolicy(LocationPolicy::class)]
class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use HasFactory;

    protected $fillable = [
        'legal_entity_id',
        'name',
        'code',
        'address',
        'neighborhood',
        'postal_code',
        'phone',
        'email',
        'opening_date',
        'opening_time',
        'closing_time',
        'timezone',
        'services',
        'status',
        'archived_at',
    ];

    protected $attributes = [
        'timezone' => 'America/Argentina/Buenos_Aires',
        'status' => OrganizationStatus::Active->value,
    ];

    /** @return BelongsTo<LegalEntity, $this> */
    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }

    public function isArchived(): bool
    {
        return $this->status === OrganizationStatus::Archived;
    }

    protected static function newFactory(): LocationFactory
    {
        return LocationFactory::new();
    }

    protected static function booted(): void
    {
        static::deleting(function (): never {
            throw new LogicException(
                'Las sedes no pueden eliminarse físicamente; deben archivarse.',
            );
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'opening_date' => 'date',
            'services' => 'array',
            'status' => OrganizationStatus::class,
            'archived_at' => 'datetime',
        ];
    }
}
