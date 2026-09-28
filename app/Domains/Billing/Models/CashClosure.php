<?php

namespace App\Domains\Billing\Models;

use Database\Factories\Domains\Billing\Models\CashClosureFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashClosure extends Model
{
    /** @use HasFactory<CashClosureFactory> */
    use HasFactory;

    protected $fillable = ['cash_register_id', 'location_id', 'legal_entity_id', 'closed_by', 'opening_amount', 'expected_amount', 'counted_amount', 'difference_amount', 'closed_at'];

    protected function casts(): array
    {
        return ['opening_amount' => 'decimal:2', 'expected_amount' => 'decimal:2', 'counted_amount' => 'decimal:2', 'difference_amount' => 'decimal:2', 'closed_at' => 'datetime'];
    }
}
