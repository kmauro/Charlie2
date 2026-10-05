<?php

use App\Domains\Billing\Models\Account;
use App\Domains\Billing\Models\FiscalConfiguration;
use App\Domains\Billing\Models\PaymentMethod;
use App\Domains\Organizations\Models\Location;
use App\Domains\Users\Models\User;

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
