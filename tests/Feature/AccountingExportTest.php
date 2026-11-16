<?php

use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\JournalEntryLine;
use App\Domains\Billing\Services\BillingService;
use App\Domains\Organizations\Models\LegalEntity;
use App\Domains\Organizations\Models\Location;
use App\Domains\Users\Enums\RoleCode;
use App\Domains\Users\Models\Role;
use App\Domains\Users\Models\User;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

it('exports only ledger lines with a verifiable location in the authorized scope', function (): void {
    $entity = LegalEntity::factory()->create();
    $allowedLocation = Location::factory()->for($entity)->create(['name' => 'Sede autorizada']);
    $otherLocation = Location::factory()->for($entity)->create(['name' => 'Sede restringida']);
    $actor = accountingUser();
    $actor->locations()->attach($allowedLocation);
    $billing = app(BillingService::class);
    $allowedInvoice = accountingInvoice($billing, $allowedLocation, $actor);
    $otherInvoice = accountingInvoice($billing, $otherLocation, $actor);

    $response = $this->actingAs($actor)->get(route('accounting.export', [
        'legal_entity_id' => $entity->id,
        'from' => now()->toDateString(),
        'to' => now()->toDateString(),
    ]));

    $response->assertSuccessful();
    $csv = $response->streamedContent();

    expect($csv)->toContain('Fecha contable', 'Sede autorizada', (string) $allowedInvoice->id)
        ->not->toContain('Sede restringida', (string) $otherInvoice->id);
});

it('denies an explicit location outside the accounting scope', function (): void {
    $entity = LegalEntity::factory()->create();
    $allowedLocation = Location::factory()->for($entity)->create();
    $restrictedLocation = Location::factory()->for($entity)->create();
    $actor = accountingUser();
    $actor->locations()->attach($allowedLocation);

    $this->actingAs($actor)->get(route('accounting.export', [
        'legal_entity_id' => $entity->id,
        'location_id' => $restrictedLocation->id,
        'from' => now()->toDateString(),
        'to' => now()->toDateString(),
    ]))->assertForbidden();
});

it('protects formula-looking accounting text in the CSV', function (): void {
    $entity = LegalEntity::factory()->create();
    $location = Location::factory()->for($entity)->create();
    $actor = accountingUser();
    $actor->locations()->attach($location);
    accountingInvoice(app(BillingService::class), $location, $actor);
    JournalEntryLine::query()->firstOrFail()->account()->update(['name' => '=FORMULA()']);

    $response = $this->actingAs($actor)->get(route('accounting.export', [
        'legal_entity_id' => $entity->id,
        'location_id' => $location->id,
        'from' => now()->toDateString(),
        'to' => now()->toDateString(),
    ]));

    expect($response->streamedContent())->toContain("'=FORMULA()");
});

it('allows accounting users to open the accounting screen', function (): void {
    $actor = accountingUser();
    $actor->locations()->attach(Location::factory()->create());

    $this->actingAs($actor)
        ->get(route('accounting.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('billing/views/Index')
            ->where('section', 'accounting')
            ->where('permissions.canManageAccounting', true)
            ->has('accountingExport'));
});

it('exports all authorized locations for an authorized legal entity', function (): void {
    $entity = LegalEntity::factory()->create();
    $firstLocation = Location::factory()->for($entity)->create(['name' => 'Sede A']);
    $secondLocation = Location::factory()->for($entity)->create(['name' => 'Sede B']);
    $actor = accountingUser();
    $actor->locations()->attach([$firstLocation->id, $secondLocation->id]);
    $billing = app(BillingService::class);
    $firstInvoice = accountingInvoice($billing, $firstLocation, $actor);
    $secondInvoice = accountingInvoice($billing, $secondLocation, $actor);

    $csv = $this->actingAs($actor)
        ->get(route('accounting.export', [
            'legal_entity_id' => $entity->id,
            'from' => now()->toDateString(),
            'to' => now()->toDateString(),
        ]))
        ->assertSuccessful()
        ->streamedContent();

    expect($csv)
        ->toContain('Sede A', (string) $firstInvoice->id)
        ->toContain('Sede B', (string) $secondInvoice->id);
});

function accountingUser(): User
{
    return User::factory()->create([
        'role_id' => Role::query()->where('code', RoleCode::Accounting->value)->value('id'),
    ]);
}

function accountingInvoice(BillingService $billing, Location $location, User $actor): Invoice
{
    return $billing->issueInvoice([
        'location_id' => $location->id,
        'payer_type' => 'patient',
        'type' => 'invoice',
        'items' => [['description' => 'Consulta', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 0]],
    ], $actor);
}
