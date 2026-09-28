<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\Account;
use App\Domains\Billing\Models\CashClosure;
use App\Domains\Billing\Models\CashRegister;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\InvoiceItem;
use App\Domains\Billing\Models\JournalEntry;
use App\Domains\Billing\Models\JournalEntryLine;
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
    public function __construct(private BillingSetupService $setup) {}

    /** @param array{location_id: int, patient_id?: int|null, appointment_id?: int|null, type: string, items: array<int, array{description: string, quantity: numeric, unit_price: numeric, tax_rate?: numeric}>, installments?: array<int, array{amount: numeric, due_date: string}>} $data */
    public function issueInvoice(array $data, User $actor): Invoice
    {
        return DB::transaction(function () use ($data, $actor): Invoice {
            $location = Location::query()->lockForUpdate()->findOrFail($data['location_id']);
            $this->setup->initializeFor(LegalEntity::query()->findOrFail($location->legal_entity_id));
            $netAmount = 0.0;
            $taxAmount = 0.0;

            foreach ($data['items'] as $item) {
                $lineNet = (float) $item['quantity'] * (float) $item['unit_price'];
                $netAmount += $lineNet;
                $taxAmount += $lineNet * ((float) ($item['tax_rate'] ?? 0) / 100);
            }

            $totalAmount = round($netAmount + $taxAmount, 2);
            $invoice = Invoice::create([
                'legal_entity_id' => $location->legal_entity_id,
                'location_id' => $location->getKey(),
                'patient_id' => $data['patient_id'] ?? null,
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

            foreach ($data['items'] as $item) {
                $lineNet = round((float) $item['quantity'] * (float) $item['unit_price'], 2);
                InvoiceItem::create([
                    'invoice_id' => $invoice->getKey(),
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'tax_rate' => $item['tax_rate'] ?? 0,
                    'total_amount' => round($lineNet * (1 + ((float) ($item['tax_rate'] ?? 0) / 100)), 2),
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
            if ($original->type !== 'invoice' || $amount <= 0 || $amount > (float) $original->total_amount) {
                throw ValidationException::withMessages(['amount' => 'La nota de crédito debe referir a una factura y no superar su total.']);
            }
            $creditNote = Invoice::create([
                'legal_entity_id' => $original->legal_entity_id, 'location_id' => $original->location_id,
                'patient_id' => $original->patient_id, 'created_by' => $actor->getKey(), 'original_invoice_id' => $original->getKey(),
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
        if ($amount <= 0 || $amount > (float) $invoice->paid_amount) {
            throw ValidationException::withMessages(['amount' => 'La devolución no puede superar lo efectivamente cobrado.']);
        }

        return Refund::create(['invoice_id' => $invoice->getKey(), 'requested_by' => $actor->getKey(), 'amount' => $amount, 'reason' => $reason, 'status' => 'pending']);
    }

    /** @param array{invoice_id: int, payment_method_id: int, amount: numeric, reference?: string|null} $data */
    public function recordPayment(array $data, User $actor): Payment
    {
        return DB::transaction(function () use ($data, $actor): Payment {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($data['invoice_id']);
            $method = PaymentMethod::query()->findOrFail($data['payment_method_id']);
            $remaining = (float) $invoice->total_amount - (float) $invoice->paid_amount;
            $amount = (float) $data['amount'];

            if ($method->legal_entity_id !== $invoice->legal_entity_id || $amount <= 0 || $amount > $remaining) {
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
            $invoice->update(['paid_amount' => (float) $invoice->paid_amount + $amount, 'status' => $remaining === $amount ? 'paid' : 'issued']);
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
            $cashPayments = Payment::query()->where('cash_register_id', $cashRegister->getKey())->where('paid_at', '>=', $cashRegister->opened_at)->sum('amount');
            $expected = (float) $cashRegister->opening_amount + (float) $cashPayments;
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
        $refund->update(['authorized_by' => $actor->getKey(), 'authorized_at' => now(), 'status' => 'authorized']);

        return $refund->refresh();
    }

    public function registerRefund(Refund $refund, User $actor): Refund
    {
        if ($refund->status !== 'authorized') {
            throw ValidationException::withMessages(['refund' => 'La devolución debe estar autorizada antes de registrarse.']);
        }
        $refund->update(['registered_by' => $actor->getKey(), 'registered_at' => now(), 'status' => 'registered']);
        $invoice = Invoice::query()->findOrFail($refund->invoice_id);
        $this->postEntry($refund, $actor, 'Devolución externa '.$invoice->number, [
            ['code' => '5.1.01', 'debit' => $refund->amount, 'credit' => 0],
            ['code' => '1.1.02', 'debit' => 0, 'credit' => $refund->amount],
        ], (int) $invoice->legal_entity_id);

        return $refund->refresh();
    }

    /** @param array<int, array{code: string, debit: numeric, credit: numeric}> $lines */
    private function postEntry(Model $source, User $actor, string $description, array $lines, ?int $legalEntityId = null): void
    {
        $legalEntityId ??= (int) $source->legal_entity_id;
        $debits = array_sum(array_map(fn (array $line): float => (float) $line['debit'], $lines));
        $credits = array_sum(array_map(fn (array $line): float => (float) $line['credit'], $lines));

        if (round($debits, 2) !== round($credits, 2)) {
            throw ValidationException::withMessages([
                'accounting' => 'El asiento automático no está balanceado.',
            ]);
        }

        $entry = JournalEntry::create(['legal_entity_id' => $legalEntityId, 'created_by' => $actor->getKey(), 'source_type' => $source::class, 'source_id' => $source->getKey(), 'entry_date' => now()->toDateString(), 'description' => $description]);
        foreach ($lines as $line) {
            $account = Account::query()->where('legal_entity_id', $legalEntityId)->where('code', $line['code'])->firstOrFail();
            JournalEntryLine::create(['journal_entry_id' => $entry->getKey(), 'account_id' => $account->getKey(), 'debit' => $line['debit'], 'credit' => $line['credit']]);
        }
    }

    private function nextNumber(int $legalEntityId, string $type): string
    {
        $sequence = Invoice::query()->where('legal_entity_id', $legalEntityId)->where('type', $type)->lockForUpdate()->count() + 1;

        return strtoupper($type).'-'.str_pad((string) $sequence, 8, '0', STR_PAD_LEFT);
    }
}
