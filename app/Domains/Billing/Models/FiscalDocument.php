<?php

namespace App\Domains\Billing\Models;

use App\Domains\Billing\Enums\FiscalDocumentStatus;
use App\Domains\Organizations\Models\Location;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FiscalDocument extends Model
{
    protected $fillable = ['invoice_id', 'location_id', 'requested_by', 'environment', 'issuer_cuit', 'point_of_sale', 'document_type', 'status', 'fiscal_number', 'fiscal_date', 'request_snapshot', 'request_hash', 'idempotency_key', 'cae', 'cae_expires_at', 'observations', 'authorized_at', 'sent_at'];
    protected $attributes = ['status' => FiscalDocumentStatus::Pending->value];
    protected function casts(): array { return ['status' => FiscalDocumentStatus::class, 'fiscal_date' => 'date', 'request_snapshot' => 'array', 'cae_expires_at' => 'date', 'authorized_at' => 'datetime', 'sent_at' => 'datetime', 'fiscal_number' => 'integer']; }
    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function location(): BelongsTo { return $this->belongsTo(Location::class); }
    public function requestedBy(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function attempts(): HasMany { return $this->hasMany(FiscalDocumentAttempt::class); }
}
