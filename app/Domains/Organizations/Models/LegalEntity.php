<?php

namespace App\Domains\Organizations\Models;

use App\Domains\Organizations\Database\Factories\LegalEntityFactory;
use App\Domains\Organizations\Enums\OrganizationStatus;
use App\Domains\Organizations\Policies\LegalEntityPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[UsePolicy(LegalEntityPolicy::class)]
class LegalEntity extends Model
{
    /** @use HasFactory<LegalEntityFactory> */
    use HasFactory;

    protected $fillable = [
        'business_name',
        'cuit',
        'fiscal_address',
        'status',
        'archived_at',
    ];

    protected $attributes = [
        'status' => OrganizationStatus::Active->value,
    ];

    /** @return HasMany<Location, $this> */
    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function isArchived(): bool
    {
        return $this->status === OrganizationStatus::Archived;
    }

    protected static function newFactory(): LegalEntityFactory
    {
        return LegalEntityFactory::new();
    }

    protected static function booted(): void
    {
        static::deleting(function (): never {
            throw new LogicException(
                'Las razones sociales no pueden eliminarse físicamente; deben archivarse.',
            );
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => OrganizationStatus::class,
            'archived_at' => 'datetime',
        ];
    }
}
