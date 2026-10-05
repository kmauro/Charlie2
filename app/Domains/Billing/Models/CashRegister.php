<?php

namespace App\Domains\Billing\Models;

use Database\Factories\Domains\Billing\Models\CashRegisterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashRegister extends Model
{
    /** @use HasFactory<CashRegisterFactory> */
    use HasFactory;

    protected $fillable = ['location_id', 'opened_by', 'opening_amount', 'opened_at'];

    protected function casts(): array
    {
        return ['opening_amount' => 'decimal:2', 'opened_at' => 'datetime'];
    }

    public function isOpen(): bool
    {
        return $this->opened_at !== null;
    }
}
