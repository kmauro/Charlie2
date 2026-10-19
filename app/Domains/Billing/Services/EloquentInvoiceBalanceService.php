<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Contracts\InvoiceBalanceService;
use App\Domains\Billing\Data\InvoiceBalance;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\Refund;

class EloquentInvoiceBalanceService implements InvoiceBalanceService
{
    public function forInvoice(Invoice $invoice): InvoiceBalance
    {
        $invoice = Invoice::query()->findOrFail($invoice->getKey());
        $invoiceId = (int) $invoice->getKey();

        $invoiceTotalCents = $this->toCents($invoice->total_amount);
        $activeCreditNotesCents = $this->toCents(Invoice::query()
            ->where('original_invoice_id', $invoiceId)
            ->where('type', 'credit_note')
            ->where('status', 'issued')
            ->sum('total_amount'));
        $paymentsCents = $this->toCents(Payment::query()
            ->where('invoice_id', $invoiceId)
            ->sum('amount'));
        $registeredRefundsCents = $this->toCents(Refund::query()
            ->where('invoice_id', $invoiceId)
            ->where('status', 'registered')
            ->sum('amount'));
        $reservedRefundsCents = $this->toCents(Refund::query()
            ->where('invoice_id', $invoiceId)
            ->whereIn('status', ['pending', 'authorized'])
            ->sum('amount'));

        return new InvoiceBalance(
            invoiceTotalCents: $invoiceTotalCents,
            activeCreditNotesCents: $activeCreditNotesCents,
            paymentsCents: $paymentsCents,
            registeredRefundsCents: $registeredRefundsCents,
            reservedRefundsCents: $reservedRefundsCents,
            receivableCents: max(0, $invoiceTotalCents - $activeCreditNotesCents - $paymentsCents),
            refundableCents: max(0, $paymentsCents - $registeredRefundsCents - $reservedRefundsCents),
        );
    }

    private function toCents(mixed $amount): int
    {
        return (int) round((float) $amount * 100);
    }
}
