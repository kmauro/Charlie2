<?php

namespace App\Domains\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialAccount extends Model
{
    protected $fillable = ['legal_entity_id', 'account_id', 'name', 'provider', 'masked_identifier', 'currency', 'kind', 'is_active'];

    protected function casts(): array { return ['is_active' => 'boolean']; }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo { return $this->belongsTo(Account::class); }
}
