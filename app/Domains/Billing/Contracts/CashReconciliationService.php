<?php

namespace App\Domains\Billing\Contracts;

use App\Domains\Billing\Data\CashMovement;
use App\Domains\Billing\Models\CashRegister;

interface CashReconciliationService
{
    /** @return list<CashMovement> */
    public function movementsForOpenPeriod(CashRegister $register): array;

    public function expectedAmountCents(CashRegister $register): int;
}
