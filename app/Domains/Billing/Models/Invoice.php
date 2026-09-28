<?php

namespace App\Domains\Billing\Models;

use Database\Factories\Domains\Billing\Models\InvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    protected $fillable = ['legal_entity_id', 'location_id', 'patient_id', 'appointment_id', 'created_by', 'original_invoice_id', 'type', 'status', 'number', 'currency', 'net_amount', 'tax_amount', 'total_amount', 'paid_amount', 'issued_at'];

    /** @return HasMany<Installment, $this> */
    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class);
    }

    protected function casts(): array
    {
        return ['net_amount' => 'decimal:2', 'tax_amount' => 'decimal:2', 'total_amount' => 'decimal:2', 'paid_amount' => 'decimal:2', 'issued_at' => 'datetime'];
    }
}
