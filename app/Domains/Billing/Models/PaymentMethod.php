<?php

namespace App\Domains\Billing\Models;

use Database\Factories\Domains\Billing\Models\PaymentMethodFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    /** @use HasFactory<PaymentMethodFactory> */
    use HasFactory;

    protected $fillable = ['legal_entity_id', 'code', 'name', 'kind', 'requires_reference', 'is_active'];

    protected function casts(): array
    {
        return ['requires_reference' => 'boolean', 'is_active' => 'boolean'];
    }
}
