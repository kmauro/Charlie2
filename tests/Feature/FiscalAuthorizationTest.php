<?php

use App\Domains\Billing\Enums\FiscalDocumentStatus;
use App\Domains\Billing\Models\FiscalConfiguration;
use App\Domains\Billing\Models\FiscalDocument;
use App\Domains\Billing\Models\JournalEntry;
use App\Domains\Billing\Services\BillingService;
use App\Domains\Billing\Services\FiscalDocumentService;
use App\Domains\Organizations\Models\Location;
use App\Domains\Users\Models\User;
use Illuminate\Validation\ValidationException;

it('authorizes an ARCA-enabled invoice before posting its accounting entry', function (): void {
    $actor = User::factory()->superAdmin()->create();
    $location = Location::factory()->create(['arca_point_of_sale' => 1]);
    FiscalConfiguration::query()->updateOrCreate(['legal_entity_id' => $location->legal_entity_id], ['arca_enabled' => true]);
    $invoice = app(BillingService::class)->issueInvoice(fiscalInvoicePayload($location), $actor);
    $document = FiscalDocument::query()->sole();

    expect($invoice->status)->toBe('pending')
        ->and(JournalEntry::query()->where('source_id', $invoice->id)->exists())->toBeFalse();

    app(FiscalDocumentService::class)->authorize($document);

    expect($document->refresh()->status)->toBe(FiscalDocumentStatus::Authorized)
        ->and($document->cae)->toStartWith('SIM')
        ->and($invoice->refresh()->status)->toBe('issued')
        ->and(JournalEntry::query()->where('source_id', $invoice->id)->count())->toBe(1);
});

it('prevents local cancellation and payment before fiscal authorization', function (): void {
    $actor = User::factory()->superAdmin()->create();
    $location = Location::factory()->create(['arca_point_of_sale' => 1]);
    FiscalConfiguration::query()->updateOrCreate(['legal_entity_id' => $location->legal_entity_id], ['arca_enabled' => true]);
    $billing = app(BillingService::class);
    $invoice = $billing->issueInvoice(fiscalInvoicePayload($location), $actor);

    expect(fn () => $billing->cancelInvoice($invoice, 'No corresponde.', $actor))->toThrow(ValidationException::class);
});

/** @return array<string, mixed> */
function fiscalInvoicePayload(Location $location): array
{
    return ['location_id' => $location->id, 'payer_type' => 'patient', 'type' => 'invoice', 'items' => [['description' => 'Prestación', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 0]]];
}
