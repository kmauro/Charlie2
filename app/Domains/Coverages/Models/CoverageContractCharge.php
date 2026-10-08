<?php

namespace App\Domains\Coverages\Models;

use App\Domains\Billing\Models\Invoice;
use Database\Factories\Domains\Coverages\Models\CoverageContractChargeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoverageContractCharge extends Model
{
    /** @use HasFactory<CoverageContractChargeFactory> */
    use HasFactory;

    protected $fillable = ['coverage_contract_id', 'invoice_id', 'due_date', 'amount', 'status'];

    protected $attributes = ['status' => 'issued'];

    /** @return BelongsTo<CoverageContract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(CoverageContract::class, 'coverage_contract_id');
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    protected function casts(): array
    {
        return ['due_date' => 'date', 'amount' => 'decimal:2'];
    }

    protected static function newFactory(): CoverageContractChargeFactory
    {
        return CoverageContractChargeFactory::new();
    }
}
