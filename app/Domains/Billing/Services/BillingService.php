<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Contracts\CashReconciliationService;
use App\Domains\Billing\Contracts\InvoiceBalanceService;
use App\Domains\Billing\Contracts\JournalPostingService;
use App\Domains\Billing\Data\JournalLine;
use App\Domains\Billing\Models\CashClosure;
use App\Domains\Billing\Models\CashRegister;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\InvoiceItem;
use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\PaymentMethod;
use App\Domains\Billing\Models\Refund;
use App\Domains\Organizations\Models\LegalEntity;
use App\Domains\Organizations\Models\Location;
use App\Domains\Users\Enums\RoleCode;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BillingService
{
    public function __construct(
        private BillingSetupService $setup,
        private InvoiceBalanceService $balances,
        private JournalPostingService $journal,
        private CashReconciliationService $cashReconciliation,
    ) {}

    /** @param array{location_id: int, patient_id?: int|null, payer_type: string, coverage_name?: string|null, coverage_amount?: numeric, appointment_id?: int|null, type: string, items: array<int, array{description: string, quantity: numeric, unit_price: numeric, tax_rate?: numeric}>, installments?: array<int, array{amount: numeric, due_date: string}>} $data */
    public function issueInvoice(array $data, User $actor): Invoice
    {
        return DB::transaction(function () use ($data, $actor): Invoice {
            $location = Location::query()->lockForUpdate()->findOrFail($data['location_id']);
            $this->setup->initializeFor(LegalEntity::query()->findOrFail($location->legal_entity_id));
            $items = array_map(function (array $item): array {
                $netAmount = round((float) $item['quantity'] * (float) $item['unit_price'], 2);
                $taxAmount = round($netAmount * ((float) ($item['tax_rate'] ?? 0) / 100), 2);

                return [...$item, 'net_amount' => $netAmount, 'tax_amount' => $taxAmount];
            }, $data['items']);
            $netAmount = round(array_sum(array_column($items, 'net_amount')), 2);
            $taxAmount = round(array_sum(array_column($items, 'tax_amount')), 2);
            $totalAmount = round($netAmount + $taxAmount, 2);
            $coverageAmount = round((float) ($data['coverage_amount'] ?? 0), 2);

            if ($data['payer_type'] === 'coverage' && $coverageAmount !== $totalAmount) {
                throw ValidationException::withMessages([
                    'coverage_amount' => 'La cobertura debe asumir el total del comprobante.',
                ]);
            }

            if ($data['payer_type'] === 'mixed' && ($coverageAmount <= 0 || $coverageAmount >= $totalAmount)) {
                throw ValidationException::withMessages([
                    'coverage_amount' => 'La cobertura debe asumir una parte del total.',
                ]);
            }

            if (isset($data['installments']) && round(array_sum(array_column($data['installments'], 'amount')), 2) !== $totalAmount) {
                throw ValidationException::withMessages([
                    'installments' => 'La suma de las cuotas debe coincidir con el total del comprobante.',
                ]);
            }

            $invoice = Invoice::create([
                'legal_entity_id' => $location->legal_entity_id,
                'location_id' => $location->getKey(),
                'patient_id' => $data['patient_id'] ?? null,
                'payer_type' => $data['payer_type'],
                'coverage_name' => $data['coverage_name'] ?? null,
                'coverage_amount' => $coverageAmount,
                'appointment_id' => $data['appointment_id'] ?? null,
                'created_by' => $actor->getKey(),
                'type' => $data['type'],
                'status' => 'issued',
                'number' => $this->nextNumber((int) $location->legal_entity_id, $data['type']),
                'currency' => 'ARS',
                'net_amount' => $netAmount,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'issued_at' => now(),
            ]);

            foreach ($items as $item) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->getKey(),
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'tax_rate' => $item['tax_rate'] ?? 0,
                    'total_amount' => $item['net_amount'] + $item['tax_amount'],
                ]);
            }

            foreach ($data['installments'] ?? [] as $index => $installment) {
                $invoice->installments()->create([
                    'number' => $index + 1,
                    'amount' => $installment['amount'],
                    'due_date' => $installment['due_date'],
                ]);
            }

            $this->postEntry($invoice, $actor, 'Emisión '.$invoice->number, [
                ['code' => '1.1.03', 'debit' => $totalAmount, 'credit' => 0],
                ['code' => '4.1.01', 'debit' => 0, 'credit' => $netAmount],
                ['code' => '2.1.01', 'debit' => 0, 'credit' => $taxAmount],
            ]);

            return $invoice;
        }, 3);
    }

    public function issueCreditNote(Invoice $invoice, float $amount, string $reason, User $actor): Invoice
    {
        return DB::transaction(function () use ($invoice, $amount, $reason, $actor): Invoice {
            $original = Invoice::query()->lockForUpdate()->findOrFail($invoice->getKey());
            Invoice::query()
                ->where('original_invoice_id', $original->getKey())
                ->where('type', 'credit_note')
                ->lockForUpdate()
                ->get();
            $balance = $this->balances->forInvoice($original);

            if ($original->status === 'canceled' || $original->type !== 'invoice' || $amount <= 0 || $this->toCents($amount) > $balance->invoiceTotalCents - $balance->activeCreditNotesCents) {
                throw ValidationException::withMessages(['amount' => 'La nota de crédito debe referir a una factura y no superar su total.']);
            }
            $creditNote = Invoice::create([
                'legal_entity_id' => $original->legal_entity_id, 'location_id' => $original->location_id,
                'patient_id' => $original->patient_id, 'payer_type' => $original->payer_type, 'coverage_name' => $original->coverage_name, 'coverage_amount' => 0, 'created_by' => $actor->getKey(), 'original_invoice_id' => $original->getKey(),
                'type' => 'credit_note', 'status' => 'issued', 'number' => $this->nextNumber((int) $original->legal_entity_id, 'credit_note'),
                'currency' => $original->currency, 'net_amount' => $amount, 'tax_amount' => 0, 'total_amount' => $amount, 'issued_at' => now(),
            ]);
            InvoiceItem::create(['invoice_id' => $creditNote->getKey(), 'description' => $reason, 'quantity' => 1, 'unit_price' => $amount, 'tax_rate' => 0, 'total_amount' => $amount]);
            $this->postEntry($creditNote, $actor, 'Nota de crédito '.$creditNote->number, [
                ['code' => '4.1.01', 'debit' => $amount, 'credit' => 0], ['code' => '1.1.03', 'debit' => 0, 'credit' => $amount],
            ]);

            return $creditNote;
        }, 3);
    }

    public function requestRefund(Invoice $invoice, float $amount, string $reason, User $actor): Refund
    {
        return DB::transaction(function () use ($invoice, $amount, $reason, $actor): Refund {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->getKey());
            Refund::query()
                ->where('invoice_id', $invoice->getKey())
                ->whereIn('status', ['pending', 'authorized', 'registered'])
                ->lockForUpdate()
                ->get();
            $balance = $this->balances->forInvoice($invoice);

            if ($invoice->status === 'canceled' || $amount <= 0 || $this->toCents($amount) > $balance->refundableCents) {
                throw ValidationException::withMessages(['amount' => 'La devolución no puede superar lo efectivamente cobrado.']);
            }

            return Refund::create(['invoice_id' => $invoice->getKey(), 'requested_by' => $actor->getKey(), 'amount' => $amount, 'reason' => $reason, 'status' => 'pending']);
        }, 3);
    }

    public function cancelInvoice(Invoice $invoice, string $reason, User $actor): Invoice
    {
        return DB::transaction(function () use ($invoice, $reason, $actor): Invoice {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->getKey());

            if ($invoice->status === 'canceled' || (float) $invoice->paid_amount > 0) {
                throw ValidationException::withMessages([
                    'invoice' => 'Sólo se pueden anular comprobantes sin cobros registrados.',
                ]);
            }

            $invoice->update([
                'status' => 'canceled',
                'canceled_by' => $actor->getKey(),
                'canceled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            $this->postCancellationEntry($invoice, $actor);

            return $invoice->refresh();
        }, 3);
    }

    /** @param array{invoice_id: int, payment_method_id: int, amount: numeric, reference?: string|null} $data */
    public function recordPayment(array $data, User $actor): Payment
    {
        return DB::transaction(function () use ($data, $actor): Payment {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($data['invoice_id']);
            $method = PaymentMethod::query()->findOrFail($data['payment_method_id']);
            $balance = $this->balances->forInvoice($invoice);
            $remaining = $this->fromCents($balance->receivableCents);
            $amount = (float) $data['amount'];

            if ($invoice->status === 'canceled' || $method->legal_entity_id !== $invoice->legal_entity_id || $amount <= 0 || $amount > $remaining) {
                throw ValidationException::withMessages(['amount' => 'El cobro debe corresponder a la factura y no superar su saldo.']);
            }
            if ($method->requires_reference && blank($data['reference'] ?? null)) {
                throw ValidationException::withMessages(['reference' => 'Este medio de pago requiere una referencia.']);
            }

            $cashRegister = null;
            if ($method->kind === 'cash') {
                $cashRegister = CashRegister::query()->where('location_id', $invoice->location_id)->lockForUpdate()->first();
                if ($cashRegister === null || ! $cashRegister->isOpen()) {
                    throw ValidationException::withMessages(['payment_method_id' => 'La caja de la sede debe estar abierta para cobrar efectivo.']);
                }
            }

            $payment = Payment::create([
                'invoice_id' => $invoice->getKey(),
                'cash_register_id' => $cashRegister?->getKey(),
                'payment_method_id' => $method->getKey(),
                'received_by' => $actor->getKey(),
                'amount' => $amount,
                'reference' => $data['reference'] ?? null,
                'paid_at' => now(),
            ]);
            $invoice->update(['paid_amount' => $this->fromCents($balance->paymentsCents + $this->toCents($amount)), 'status' => $this->toCents($amount) === $balance->receivableCents ? 'paid' : 'issued']);
            $this->postEntry($payment, $actor, 'Cobro '.$invoice->number, [
                ['code' => $method->kind === 'cash' ? '1.1.01' : '1.1.02', 'debit' => $amount, 'credit' => 0],
                ['code' => '1.1.03', 'debit' => 0, 'credit' => $amount],
            ], (int) $invoice->legal_entity_id);

            return $payment;
        }, 3);
    }

    public function openCashRegister(Location $location, float $openingAmount, User $actor): CashRegister
    {
        return DB::transaction(function () use ($location, $openingAmount, $actor): CashRegister {
            $cashRegister = CashRegister::query()->lockForUpdate()->firstOrCreate(['location_id' => $location->getKey()]);
            if ($cashRegister->isOpen()) {
                throw ValidationException::withMessages(['location_id' => 'La caja de esta sede ya está abierta.']);
            }
            $cashRegister->update(['opened_by' => $actor->getKey(), 'opening_amount' => $openingAmount, 'opened_at' => now()]);

            return $cashRegister->refresh();
        }, 3);
    }

    public function closeCashRegister(Location $location, float $countedAmount, User $actor): CashClosure
    {
        return DB::transaction(function () use ($location, $countedAmount, $actor): CashClosure {
            $cashRegister = CashRegister::query()->where('location_id', $location->getKey())->lockForUpdate()->firstOrFail();
            if (! $cashRegister->isOpen()) {
                throw ValidationException::withMessages(['location_id' => 'La caja de esta sede no está abierta.']);
            }
            $expected = $this->fromCents($this->cashReconciliation->expectedAmountCents($cashRegister));
            $closure = CashClosure::create([
                'cash_register_id' => $cashRegister->getKey(), 'location_id' => $location->getKey(), 'legal_entity_id' => $location->legal_entity_id,
                'closed_by' => $actor->getKey(), 'opening_amount' => $cashRegister->opening_amount, 'expected_amount' => $expected,
                'counted_amount' => $countedAmount, 'difference_amount' => $countedAmount - $expected, 'closed_at' => now(),
            ]);
            $cashRegister->update(['opened_by' => null, 'opening_amount' => 0, 'opened_at' => null]);

            return $closure;
        }, 3);
    }

    public function authorizeRefund(Refund $refund, User $actor): Refund
    {
        if (! in_array($actor->role?->code, [RoleCode::SuperAdmin, RoleCode::Manager], true)) {
            throw ValidationException::withMessages(['authorization' => 'La devolución requiere autorización de Gerencia o Superadministración.']);
        }

        return DB::transaction(function () use ($refund, $actor): Refund {
            $refund = Refund::query()->lockForUpdate()->findOrFail($refund->getKey());
            if ($refund->status !== 'pending') {
                throw ValidationException::withMessages(['refund' => 'Sólo se pueden autorizar devoluciones pendientes.']);
            }

            $refund->update(['authorized_by' => $actor->getKey(), 'authorized_at' => now(), 'status' => 'authorized']);

            return $refund->refresh();
        }, 3);
    }

    public function registerRefund(Refund $refund, User $actor, ?int $paymentMethodId = null, ?string $reference = null): Refund
    {
        return DB::transaction(function () use ($refund, $actor, $paymentMethodId, $reference): Refund {
            $refund = Refund::query()->lockForUpdate()->findOrFail($refund->getKey());
            if ($refund->status !== 'authorized') {
                throw ValidationException::withMessages(['refund' => 'La devolución debe estar autorizada antes de registrarse.']);
            }

            $invoice = Invoice::query()->lockForUpdate()->findOrFail($refund->invoice_id);
            $paymentMethod = PaymentMethod::query()->findOrFail($paymentMethodId ?? Payment::query()
                ->where('invoice_id', $invoice->getKey())
                ->latest('paid_at')
                ->value('payment_method_id'));

            if ($paymentMethod->legal_entity_id !== $invoice->legal_entity_id) {
                throw ValidationException::withMessages(['payment_method_id' => 'El medio de devolución debe pertenecer a la entidad de la factura.']);
            }
            if ($paymentMethod->requires_reference && blank($reference)) {
                throw ValidationException::withMessages(['reference' => 'Este medio de devolución requiere una referencia.']);
            }

            $cashRegister = null;
            if ($paymentMethod->kind === 'cash') {
                $cashRegister = CashRegister::query()
                    ->where('location_id', $invoice->location_id)
                    ->lockForUpdate()
                    ->first();
                if ($cashRegister === null || ! $cashRegister->isOpen()) {
                    throw ValidationException::withMessages(['payment_method_id' => 'La caja de la sede debe estar abierta para devolver efectivo.']);
                }
            }

            $refund->update([
                'registered_by' => $actor->getKey(),
                'payment_method_id' => $paymentMethod->getKey(),
                'cash_register_id' => $cashRegister?->getKey(),
                'reference' => $reference,
                'registered_at' => now(),
                'status' => 'registered',
            ]);
            $invoice->update(['status' => 'issued']);
            $this->postEntry($refund, $actor, 'Devolución externa '.$invoice->number, [
                ['code' => '5.1.01', 'debit' => $refund->amount, 'credit' => 0],
                ['code' => $paymentMethod->kind === 'cash' ? '1.1.01' : '1.1.02', 'debit' => 0, 'credit' => $refund->amount],
            ], (int) $invoice->legal_entity_id);

            return $refund->refresh();
        }, 3);
    }

    /** @param array<int, array{code: string, debit: numeric, credit: numeric}> $lines */
    private function postEntry(Model $source, User $actor, string $description, array $lines, ?int $legalEntityId = null): void
    {
        $legalEntityId ??= (int) $source->legal_entity_id;
        $this->journal->post(
            source: $source,
            legalEntityId: $legalEntityId,
            actor: $actor,
            description: $description,
            lines: array_map(fn (array $line): JournalLine => new JournalLine(
                accountCode: $line['code'],
                debitCents: $this->toCents($line['debit']),
                creditCents: $this->toCents($line['credit']),
            ), $lines),
        );
    }

    private function nextNumber(int $legalEntityId, string $type): string
    {
        LegalEntity::query()->lockForUpdate()->findOrFail($legalEntityId);
        $sequence = Invoice::query()->where('legal_entity_id', $legalEntityId)->where('type', $type)->lockForUpdate()->count() + 1;

        return strtoupper($type).'-'.str_pad((string) $sequence, 8, '0', STR_PAD_LEFT);
    }

    private function postCancellationEntry(Invoice $invoice, User $actor): void
    {
        if ($invoice->type === 'credit_note') {
            $this->postEntry($invoice, $actor, 'Anulación '.$invoice->number, [
                ['code' => '1.1.03', 'debit' => $invoice->total_amount, 'credit' => 0],
                ['code' => '4.1.01', 'debit' => 0, 'credit' => $invoice->net_amount],
                ['code' => '2.1.01', 'debit' => 0, 'credit' => $invoice->tax_amount],
            ]);

            return;
        }

        $this->postEntry($invoice, $actor, 'Anulación '.$invoice->number, [
            ['code' => '4.1.01', 'debit' => $invoice->net_amount, 'credit' => 0],
            ['code' => '2.1.01', 'debit' => $invoice->tax_amount, 'credit' => 0],
            ['code' => '1.1.03', 'debit' => 0, 'credit' => $invoice->total_amount],
        ]);
    }

    private function toCents(mixed $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    private function fromCents(int $amount): float
    {
        return $amount / 100;
    }
}
