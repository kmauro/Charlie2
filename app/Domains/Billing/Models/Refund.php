<?php

namespace App\Domains\Billing\Models;

use Database\Factories\Domains\Billing\Models\RefundFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    /** @use HasFactory<RefundFactory> */
    use HasFactory;

    protected $fillable = ['invoice_id', 'requested_by', 'authorized_by', 'registered_by', 'amount', 'reason', 'status', 'authorized_at', 'registered_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'authorized_at' => 'datetime', 'registered_at' => 'datetime'];
    }
}
