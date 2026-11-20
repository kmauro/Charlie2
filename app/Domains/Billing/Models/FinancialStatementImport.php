<?php

namespace App\Domains\Billing\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialStatementImport extends Model
{
    protected $fillable = ['financial_account_id', 'imported_by', 'file_name', 'file_hash', 'period_from', 'period_to', 'row_count', 'status'];
    protected function casts(): array { return ['period_from' => 'date', 'period_to' => 'date']; }
}
