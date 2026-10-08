<?php

namespace App\Domains\Coverages\Models;

use Database\Factories\Domains\Coverages\Models\CoveragePlanBenefitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoveragePlanBenefit extends Model
{
    /** @use HasFactory<CoveragePlanBenefitFactory> */
    use HasFactory;

    protected $fillable = ['coverage_plan_id', 'name', 'coverage_percentage', 'copay_amount', 'annual_limit', 'is_active'];

    protected $attributes = ['coverage_percentage' => 100, 'is_active' => true];

    /** @return BelongsTo<CoveragePlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(CoveragePlan::class, 'coverage_plan_id');
    }

    protected function casts(): array
    {
        return ['coverage_percentage' => 'decimal:2', 'copay_amount' => 'decimal:2', 'annual_limit' => 'integer', 'is_active' => 'boolean'];
    }

    protected static function newFactory(): CoveragePlanBenefitFactory
    {
        return CoveragePlanBenefitFactory::new();
    }
}
