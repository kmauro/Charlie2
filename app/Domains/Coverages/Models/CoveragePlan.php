<?php

namespace App\Domains\Coverages\Models;

use App\Domains\Organizations\Models\LegalEntity;
use Database\Factories\Domains\Coverages\Models\CoveragePlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoveragePlan extends Model
{
    /** @use HasFactory<CoveragePlanFactory> */
    use HasFactory;

    protected $fillable = ['legal_entity_id', 'name', 'code', 'billing_cycle', 'recurring_amount', 'is_active'];

    protected $attributes = ['billing_cycle' => 'monthly', 'is_active' => true];

    /** @return BelongsTo<LegalEntity, $this> */
    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }

    /** @return HasMany<CoveragePlanBenefit, $this> */
    public function benefits(): HasMany
    {
        return $this->hasMany(CoveragePlanBenefit::class);
    }

    /** @return HasMany<CoverageContract, $this> */
    public function contracts(): HasMany
    {
        return $this->hasMany(CoverageContract::class);
    }

    protected function casts(): array
    {
        return ['recurring_amount' => 'decimal:2', 'is_active' => 'boolean'];
    }

    protected static function newFactory(): CoveragePlanFactory
    {
        return CoveragePlanFactory::new();
    }
}
