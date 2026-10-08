<?php

namespace App\Domains\Coverages\Models;

use App\Domains\Organizations\Models\LegalEntity;
use App\Domains\Organizations\Models\Location;
use App\Domains\Patients\Models\Patient;
use App\Domains\Users\Models\User;
use Database\Factories\Domains\Coverages\Models\CoverageContractFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoverageContract extends Model
{
    /** @use HasFactory<CoverageContractFactory> */
    use HasFactory;

    protected $fillable = ['legal_entity_id', 'location_id', 'patient_id', 'coverage_plan_id', 'created_by', 'member_number', 'payer_type', 'coverage_amount', 'recurring_amount', 'starts_on', 'ends_on', 'next_charge_on', 'status'];

    protected $attributes = ['payer_type' => 'patient', 'coverage_amount' => 0, 'status' => 'active'];

    /** @return BelongsTo<LegalEntity, $this> */
    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<CoveragePlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(CoveragePlan::class, 'coverage_plan_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<CoverageContractCharge, $this> */
    public function charges(): HasMany
    {
        return $this->hasMany(CoverageContractCharge::class);
    }

    public function isChargeableOn(string $date): bool
    {
        return $this->status === 'active' && $this->next_charge_on?->toDateString() <= $date && ($this->ends_on === null || $this->ends_on->toDateString() >= $date);
    }

    protected function casts(): array
    {
        return ['coverage_amount' => 'decimal:2', 'recurring_amount' => 'decimal:2', 'starts_on' => 'date', 'ends_on' => 'date', 'next_charge_on' => 'date'];
    }

    protected static function newFactory(): CoverageContractFactory
    {
        return CoverageContractFactory::new();
    }
}
