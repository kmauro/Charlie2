<?php

use App\Domains\Billing\Models\Account;
use App\Domains\Billing\Models\FiscalConfiguration;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\JournalEntry;
use App\Domains\Billing\Models\JournalEntryLine;
use App\Domains\Billing\Models\PaymentMethod;
use App\Domains\Billing\Services\BillingService;
use App\Domains\Organizations\Models\Location;
use App\Domains\Users\Models\User;
use Illuminate\Validation\ValidationException;

it('initializes a balanced billing setup when creating a legal entity', function () {
    $actor = User::factory()->superAdmin()->create();

    $this->actingAs($actor)->post(route('legal_entities.store'), [
        'business_name' => 'Clínica Financiera S.A.',
        'cuit' => '30707216650',
        'tax_condition' => 'responsable_inscripto',
        'fiscal_address' => 'Av. Corrientes 1000, CABA',
        'fiscal_point_of_sale' => 1,
        'arca_environment' => 'homologacion',
        'status' => 'active',
    ])->assertRedirect(route('legal_entities.index'));

    expect(FiscalConfiguration::query()->count())->toBe(1)
        ->and(Account::query()->pluck('code')->all())->toContain('1.1.01', '1.1.02', '1.1.03', '2.1.01', '4.1.01', '5.1.01')
        ->and(PaymentMethod::query()->pluck('code')->all())->toContain('cash', 'bank_transfer', 'mercado_pago_qr');
});

it('allows a superadmin to open billing without explicit location assignments', function () {
    $actor = User::factory()->superAdmin()->create();

    $this->actingAs($actor)
        ->get(route('billing.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('billing/views/Index')
            ->has('locations')
            ->has('patients')
            ->has('paymentMethods')
            ->has('cashClosures')
            ->has('permissions.canManageAccounting'));
});

it('filters billing data by the selected organization location', function () {
    $actor = User::factory()->superAdmin()->create();
    $selectedLocation = Location::factory()->create();
    Location::factory()->create();

    $this->actingAs($actor)
        ->withSession(['selected_organization_location_id' => $selectedLocation->getKey()])
        ->get(route('billing.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('locations', 1)
            ->where('locations.0.id', $selectedLocation->getKey()));
});

it('stores an accessible organization location in the session', function () {
    $actor = User::factory()->superAdmin()->create();
    $location = Location::factory()->create();

    $this->actingAs($actor)
        ->post(route('organization-context.store'), ['location_id' => $location->getKey()])
        ->assertRedirect()
        ->assertSessionHas('selected_organization_location_id', $location->getKey());
});

it('reverses the accounting entry when canceling an unpaid invoice', function () {
    $actor = User::factory()->superAdmin()->create();
    $location = Location::factory()->create();
    $billing = app(BillingService::class);

    $invoice = issueBillingInvoice($billing, $location, $actor, 100);
    $billing->cancelInvoice($invoice, 'Prestación no realizada.', $actor);

    expect($invoice->refresh()->status)->toBe('canceled')
        ->and(JournalEntry::query()->where('source_type', Invoice::class)->where('source_id', $invoice->getKey())->count())->toBe(2)
        ->and((float) JournalEntryLine::query()
            ->whereHas('account', fn ($query) => $query
                ->where('legal_entity_id', $location->legal_entity_id)
                ->where('code', '1.1.03'))
            ->sum('debit'))->toBe(100.0);
});

it('does not allow credit notes to exceed the original invoice total', function () {
    $actor = User::factory()->superAdmin()->create();
    $location = Location::factory()->create();
    $billing = app(BillingService::class);
    $invoice = issueBillingInvoice($billing, $location, $actor, 100);

    $billing->issueCreditNote($invoice, 60, 'Cobro incorrecto.', $actor);

    expect(fn () => $billing->issueCreditNote($invoice, 50, 'Segundo ajuste.', $actor))
        ->toThrow(ValidationException::class);
});

it('prevents refunds from exceeding the amount collected and returns the invoice to issued', function () {
    $actor = User::factory()->superAdmin()->create();
    $location = Location::factory()->create();
    $billing = app(BillingService::class);
    $invoice = issueBillingInvoice($billing, $location, $actor, 100);
    $billing->openCashRegister($location, 0, $actor);
    $billing->recordPayment([
        'invoice_id' => $invoice->getKey(),
        'payment_method_id' => PaymentMethod::query()->where('legal_entity_id', $location->legal_entity_id)->where('code', 'cash')->value('id'),
        'amount' => 100,
    ], $actor);

    $refund = $billing->requestRefund($invoice, 60, 'Devolución parcial.', $actor);

    expect(fn () => $billing->requestRefund($invoice, 50, 'Excede lo cobrado.', $actor))
        ->toThrow(ValidationException::class);

    $billing->authorizeRefund($refund, $actor);
    $billing->registerRefund($refund, $actor);

    expect((float) $invoice->refresh()->paid_amount)->toBe(40.0)
        ->and($invoice->status)->toBe('issued')
        ->and($refund->refresh()->status)->toBe('registered');
});

it('requires installments to match the invoice total', function () {
    $actor = User::factory()->superAdmin()->create();
    $location = Location::factory()->create();
    $billing = app(BillingService::class);

    expect(fn () => $billing->issueInvoice([
        'location_id' => $location->getKey(),
        'payer_type' => 'patient',
        'type' => 'invoice',
        'items' => [['description' => 'Consulta', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 0]],
        'installments' => [['amount' => 90, 'due_date' => now()->addMonth()->toDateString()]],
    ], $actor))->toThrow(ValidationException::class);
});

function issueBillingInvoice(BillingService $billing, Location $location, User $actor, float $amount): Invoice
{
    return $billing->issueInvoice([
        'location_id' => $location->getKey(),
        'payer_type' => 'patient',
        'type' => 'invoice',
        'items' => [['description' => 'Consulta', 'quantity' => 1, 'unit_price' => $amount, 'tax_rate' => 0]],
    ], $actor);
}
