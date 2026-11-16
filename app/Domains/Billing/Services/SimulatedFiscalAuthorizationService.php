<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Contracts\FiscalAuthorizationService;
use App\Domains\Billing\Data\FiscalAuthorizationResult;
use App\Domains\Billing\Models\FiscalDocument;

class SimulatedFiscalAuthorizationService implements FiscalAuthorizationService
{
    public function lastAuthorizedNumber(FiscalDocument $document): int { return (int) ($document->fiscal_number ?? 0) - 1; }
    public function authorize(FiscalDocument $document): FiscalAuthorizationResult
    {
        return new FiscalAuthorizationResult(true, 'SIM'.str_pad((string) $document->fiscal_number, 11, '0', STR_PAD_LEFT), now()->addDays(10)->toDateString(), '0', 'Autorizado por simulador.');
    }
    public function find(FiscalDocument $document): ?FiscalAuthorizationResult { return $document->status->value === 'authorized' ? new FiscalAuthorizationResult(true, $document->cae, $document->cae_expires_at?->toDateString()) : null; }
}
