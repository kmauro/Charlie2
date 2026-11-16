<?php

namespace App\Domains\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FiscalDocumentAttempt extends Model
{
    protected $fillable = ['fiscal_document_id', 'status', 'fiscal_number', 'provider_code', 'message', 'correlation_id', 'metadata'];
    protected function casts(): array { return ['fiscal_number' => 'integer', 'metadata' => 'array']; }
    public function fiscalDocument(): BelongsTo { return $this->belongsTo(FiscalDocument::class); }
}
