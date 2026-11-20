<?php

namespace App\Domains\Billing\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalFinancialMovement extends Model
{
    protected $fillable = ['financial_statement_import_id', 'financial_account_id', 'external_id', 'operated_at', 'settled_at', 'amount', 'currency', 'reference', 'normalized_reference', 'description', 'source_row'];
    protected function casts(): array { return ['operated_at' => 'date', 'settled_at' => 'date', 'amount' => 'decimal:2']; }
}
