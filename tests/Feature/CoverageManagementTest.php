<?php

use App\Domains\Billing\Models\Invoice;
use App\Domains\Coverages\Models\CoverageContractCharge;
use App\Domains\Coverages\Models\CoveragePlan;
use App\Domains\Coverages\Services\CoverageService;
use App\Domains\Organizations\Models\Location;
use App\Domains\Patients\Models\Patient;
use App\Domains\Users\Models\User;

it('creates plan benefits and invoices an eligible recurring contract once', function () {
    $actor = User::factory()->superAdmin()->create();
    $location = Location::factory()->create();
    $patient = Patient::factory()->create();
    $coverage = app(CoverageService::class);

    $plan = $coverage->createPlan([
        'legal_entity_id' => $location->legal_entity_id,
        'name' => 'Plan Integral',
        'code' => 'INTEGRAL',
        'billing_cycle' => 'monthly',
        'recurring_amount' => 15000,
    ]);
    $coverage->addBenefit($plan, [
        'name' => 'Consulta clínica',
        'coverage_percentage' => 80,
        'copay_amount' => 3000,
    ]);
    $contract = $coverage->createContract([
        'location_id' => $location->getKey(),
        'patient_id' => $patient->getKey(),
        'coverage_plan_id' => $plan->getKey(),
        'member_number' => 'A-123',
        'payer_type' => 'coverage',
        'coverage_amount' => 15000,
        'recurring_amount' => 15000,
        'starts_on' => today()->toDateString(),
    ], $actor);

    expect($coverage->generateDueCharges($contract->getKey()))->toBe(1)
        ->and($coverage->generateDueCharges($contract->getKey()))->toBe(0)
        ->and($plan->benefits)->toHaveCount(1)
        ->and(CoverageContractCharge::query()->whereBelongsTo($contract, 'contract')->count())->toBe(1)
        ->and(Invoice::query()->where('patient_id', $patient->getKey())->value('coverage_name'))->toBe('Plan Integral')
        ->and($contract->refresh()->next_charge_on->toDateString())->toBe(today()->addMonthNoOverflow()->toDateString());
});

it('stores a coverage plan through the management endpoint', function () {
    $actor = User::factory()->superAdmin()->create();
    $location = Location::factory()->create();

    $this->actingAs($actor)
        ->post(route('coverages.plans.store'), [
            'legal_entity_id' => $location->legal_entity_id,
            'name' => 'Plan QA 2026',
            'code' => 'QA-2026',
            'billing_cycle' => 'monthly',
            'recurring_amount' => 1000,
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'El plan de cobertura fue creado.');

    $plan = CoveragePlan::query()
        ->where('legal_entity_id', $location->legal_entity_id)
        ->where('code', 'QA-2026')
        ->sole();

    expect($plan->name)->toBe('Plan QA 2026');
});
