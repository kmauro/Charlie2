<?php

namespace App\Domains\Billing\Models;

use Database\Factories\Domains\Billing\Models\PaymentMethodFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentMethod extends Model
{
    /** @use HasFactory<PaymentMethodFactory> */
    use HasFactory;

    protected $fillable = ['legal_entity_id', 'financial_account_id', 'code', 'name', 'kind', 'requires_reference', 'is_active'];

    /** @return BelongsTo<FinancialAccount, $this> */
    public function financialAccount(): BelongsTo { return $this->belongsTo(FinancialAccount::class); }

    protected function casts(): array
    {
        return ['requires_reference' => 'boolean', 'is_active' => 'boolean'];
    }
}
