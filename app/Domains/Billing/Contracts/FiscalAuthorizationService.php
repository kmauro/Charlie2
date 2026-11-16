<?php

namespace App\Domains\Billing\Contracts;

use App\Domains\Billing\Data\FiscalAuthorizationResult;
use App\Domains\Billing\Models\FiscalDocument;

interface FiscalAuthorizationService
{
    public function lastAuthorizedNumber(FiscalDocument $document): int;
    public function authorize(FiscalDocument $document): FiscalAuthorizationResult;
    public function find(FiscalDocument $document): ?FiscalAuthorizationResult;
}
