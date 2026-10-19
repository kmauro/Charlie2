<?php

use App\Domains\Billing\Contracts\InvoiceBalanceService;
use App\Domains\Billing\Models\CashClosure;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\JournalEntry;
use App\Domains\Billing\Models\PaymentMethod;
use App\Domains\Billing\Models\Refund;
use App\Domains\Billing\Services\BillingService;
use App\Domains\Organizations\Models\Location;
use App\Domains\Users\Models\User;
use Illuminate\Validation\ValidationException;

it('reconciles an invoice after partial and final payments', function (): void {
    [$billing, $location, $actor, $invoice] = financialFlowContext(100);
    $methodId = financialPaymentMethodId($location, 'bank_transfer');

    $billing->recordPayment(['invoice_id' => $invoice->getKey(), 'payment_method_id' => $methodId, 'amount' => 40, 'reference' => 'COBRO-001'], $actor);

    expect(app(InvoiceBalanceService::class)->forInvoice($invoice)->receivableCents)->toBe(6000);

    $billing->recordPayment(['invoice_id' => $invoice->getKey(), 'payment_method_id' => $methodId, 'amount' => 60, 'reference' => 'COBRO-002'], $actor);

    expect($invoice->refresh()->status)->toBe('paid')
        ->and(app(InvoiceBalanceService::class)->forInvoice($invoice)->receivableCents)->toBe(0);
});

it('limits successive credit notes to the original invoice total', function (): void {
    [$billing, , $actor, $invoice] = financialFlowContext(100);

    $billing->issueCreditNote($invoice, 60, 'Ajuste inicial.', $actor);

    expect(fn () => $billing->issueCreditNote($invoice, 41, 'Excede el saldo.', $actor))
        ->toThrow(ValidationException::class);
});

it('accepts sequential partial refunds without counting registered refunds twice', function (): void {
    [$billing, $location, $actor, $invoice] = financialFlowContext(100);
    $methodId = financialPaymentMethodId($location, 'bank_transfer');
    $billing->recordPayment(['invoice_id' => $invoice->getKey(), 'payment_method_id' => $methodId, 'amount' => 100, 'reference' => 'COBRO-003'], $actor);

    $firstRefund = $billing->requestRefund($invoice, 60, 'Primera devolución.', $actor);
    $billing->authorizeRefund($firstRefund, $actor);
    $billing->registerRefund($firstRefund, $actor, $methodId, 'TRX-001');
    $secondRefund = $billing->requestRefund($invoice, 40, 'Segunda devolución.', $actor);
    $billing->authorizeRefund($secondRefund, $actor);
    $billing->registerRefund($secondRefund, $actor, $methodId, 'TRX-002');

    expect(app(InvoiceBalanceService::class)->forInvoice($invoice)->registeredRefundsCents)->toBe(10000)
        ->and(app(InvoiceBalanceService::class)->forInvoice($invoice)->refundableCents)->toBe(0);
});

it('reserves pending and authorized refunds against the refundable amount', function (): void {
    [$billing, $location, $actor, $invoice] = financialFlowContext(100);
    $methodId = financialPaymentMethodId($location, 'bank_transfer');
    $billing->recordPayment(['invoice_id' => $invoice->getKey(), 'payment_method_id' => $methodId, 'amount' => 100, 'reference' => 'COBRO-004'], $actor);

    $pendingRefund = $billing->requestRefund($invoice, 60, 'Pendiente.', $actor);
    $authorizedRefund = $billing->requestRefund($invoice, 40, 'A autorizar.', $actor);
    $billing->authorizeRefund($authorizedRefund, $actor);

    expect(app(InvoiceBalanceService::class)->forInvoice($invoice)->reservedRefundsCents)->toBe(10000)
        ->and(fn () => $billing->requestRefund($invoice, 1, 'Excedente.', $actor))->toThrow(ValidationException::class)
        ->and($pendingRefund->refresh()->status)->toBe('pending');
});

it('subtracts only cash refunds from the matching cash register closure', function (): void {
    [$billing, $location, $actor, $invoice] = financialFlowContext(100);
    $cashMethodId = financialPaymentMethodId($location, 'cash');
    $bankMethodId = financialPaymentMethodId($location, 'bank_transfer');
    $billing->openCashRegister($location, 0, $actor);
    $billing->recordPayment(['invoice_id' => $invoice->getKey(), 'payment_method_id' => $cashMethodId, 'amount' => 100], $actor);

    $cashRefund = $billing->requestRefund($invoice, 20, 'Salida de caja.', $actor);
    $billing->authorizeRefund($cashRefund, $actor);
    $billing->registerRefund($cashRefund, $actor, $cashMethodId);
    $bankRefund = $billing->requestRefund($invoice, 10, 'Transferencia.', $actor);
    $billing->authorizeRefund($bankRefund, $actor);
    $billing->registerRefund($bankRefund, $actor, $bankMethodId, 'TRX-003');
    $closure = $billing->closeCashRegister($location, 80, $actor);

    expect((float) $closure->expected_amount)->toBe(80.0)
        ->and($cashRefund->refresh()->cash_register_id)->not->toBeNull()
        ->and($bankRefund->refresh()->cash_register_id)->toBeNull();
});

it('reverses the journal entry when canceling an unpaid invoice', function (): void {
    [$billing, , $actor, $invoice] = financialFlowContext(100);

    $billing->cancelInvoice($invoice, 'La prestación no ocurrió.', $actor);

    expect($invoice->refresh()->status)->toBe('canceled')
        ->and(JournalEntry::query()->where('source_type', Invoice::class)->where('source_id', $invoice->getKey())->count())->toBe(2);
});

it('rejects operations outside the user location and permissions without financial writes', function (): void {
    [$billing, $location, $actor, $invoice] = financialFlowContext(100);
    $methodId = financialPaymentMethodId($location, 'bank_transfer');
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->post(route('billing.payments.store'), ['invoice_id' => $invoice->getKey(), 'payment_method_id' => $methodId, 'amount' => 100])
        ->assertForbidden();

    expect($invoice->refresh()->paid_amount)->toEqual('0.00')
        ->and(JournalEntry::query()->where('source_type', Invoice::class)->where('source_id', $invoice->getKey())->count())->toBe(1)
        ->and($billing)->toBeInstanceOf(BillingService::class);
});

it('does not duplicate refunds cash closures or journal entries on repeated transitions', function (): void {
    [$billing, $location, $actor, $invoice] = financialFlowContext(100);
    $methodId = financialPaymentMethodId($location, 'bank_transfer');
    $billing->recordPayment(['invoice_id' => $invoice->getKey(), 'payment_method_id' => $methodId, 'amount' => 100, 'reference' => 'COBRO-005'], $actor);
    $refund = $billing->requestRefund($invoice, 100, 'Devolución total.', $actor);
    $billing->authorizeRefund($refund, $actor);
    $billing->registerRefund($refund, $actor, $methodId, 'TRX-004');

    expect(fn () => $billing->authorizeRefund($refund, $actor))->toThrow(ValidationException::class)
        ->and(fn () => $billing->registerRefund($refund, $actor, $methodId, 'TRX-004'))->toThrow(ValidationException::class)
        ->and(Refund::query()->count())->toBe(1)
        ->and(JournalEntry::query()->where('source_type', Refund::class)->where('source_id', $refund->getKey())->count())->toBe(1)
        ->and(CashClosure::query()->count())->toBe(0);
});

/** @return array{BillingService, Location, User, Invoice} */
function financialFlowContext(float $amount): array
{
    $actor = User::factory()->superAdmin()->create();
    $location = Location::factory()->create();
    $billing = app(BillingService::class);
    $invoice = $billing->issueInvoice([
        'location_id' => $location->getKey(),
        'payer_type' => 'patient',
        'type' => 'invoice',
        'items' => [['description' => 'Consulta', 'quantity' => 1, 'unit_price' => $amount, 'tax_rate' => 0]],
    ], $actor);

    return [$billing, $location, $actor, $invoice];
}

function financialPaymentMethodId(Location $location, string $code): int
{
    return (int) PaymentMethod::query()
        ->where('legal_entity_id', $location->legal_entity_id)
        ->where('code', $code)
        ->value('id');
}
