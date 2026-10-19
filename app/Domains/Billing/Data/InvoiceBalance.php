<?php

namespace App\Domains\Billing\Data;

final readonly class InvoiceBalance
{
    public function __construct(
        public int $invoiceTotalCents,
        public int $activeCreditNotesCents,
        public int $paymentsCents,
        public int $registeredRefundsCents,
        public int $reservedRefundsCents,
        public int $receivableCents,
        public int $refundableCents,
    ) {}
}
