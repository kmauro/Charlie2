<?php

namespace App\Domains\Billing\Models;

use Database\Factories\Domains\Billing\Models\RefundFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    /** @use HasFactory<RefundFactory> */
    use HasFactory;

    protected $fillable = ['invoice_id', 'requested_by', 'authorized_by', 'registered_by', 'payment_method_id', 'cash_register_id', 'amount', 'reference', 'reason', 'status', 'authorized_at', 'registered_at'];

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<PaymentMethod, $this> */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    /** @return BelongsTo<CashRegister, $this> */
    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'authorized_at' => 'datetime', 'registered_at' => 'datetime'];
    }
}
