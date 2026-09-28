<?php

namespace App\Domains\Organizations\Models;

use App\Domains\Organizations\Enums\OrganizationStatus;
use App\Domains\Organizations\Policies\LegalEntityPolicy;
use Database\Factories\LegalEntityFactory;
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
        'tax_condition',
        'fiscal_address',
        'fiscal_point_of_sale',
        'arca_environment',
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
            'fiscal_point_of_sale' => 'integer',
            'status' => OrganizationStatus::class,
            'archived_at' => 'datetime',
        ];
    }
}
