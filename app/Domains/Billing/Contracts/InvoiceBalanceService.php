<?php

namespace App\Domains\Billing\Contracts;

use App\Domains\Billing\Data\InvoiceBalance;
use App\Domains\Billing\Models\Invoice;

interface InvoiceBalanceService
{
    public function forInvoice(Invoice $invoice): InvoiceBalance;
}
