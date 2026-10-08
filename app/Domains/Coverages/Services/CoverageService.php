<?php

namespace App\Domains\Coverages\Services;

use App\Domains\Billing\Services\BillingService;
use App\Domains\Coverages\Models\CoverageContract;
use App\Domains\Coverages\Models\CoverageContractCharge;
use App\Domains\Coverages\Models\CoveragePlan;
use App\Domains\Coverages\Models\CoveragePlanBenefit;
use App\Domains\Organizations\Models\Location;
use App\Domains\Users\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CoverageService
{
    public function __construct(private BillingService $billing) {}

    /** @param array{legal_entity_id: int, name: string, code: string, billing_cycle: string, recurring_amount: numeric} $data */
    public function createPlan(array $data): CoveragePlan
    {
        return CoveragePlan::create($data);
    }

    /** @param array{name: string, coverage_percentage: numeric, copay_amount?: numeric|null, annual_limit?: int|null} $data */
    public function addBenefit(CoveragePlan $plan, array $data): CoveragePlanBenefit
    {
        return $plan->benefits()->create($data);
    }

    /** @param array{location_id: int, patient_id: int, coverage_plan_id: int, member_number?: string|null, payer_type: string, coverage_amount?: numeric, recurring_amount: numeric, starts_on: string, ends_on?: string|null} $data */
    public function createContract(array $data, User $actor): CoverageContract
    {
        return DB::transaction(function () use ($data, $actor): CoverageContract {
            $location = Location::query()->findOrFail($data['location_id']);
            $plan = CoveragePlan::query()->findOrFail($data['coverage_plan_id']);
            $amount = round((float) $data['recurring_amount'], 2);
            $coverageAmount = round((float) ($data['coverage_amount'] ?? 0), 2);

            if ($plan->legal_entity_id !== $location->legal_entity_id || ! $plan->is_active) {
                throw ValidationException::withMessages(['coverage_plan_id' => 'El plan debe estar activo y pertenecer a la razón social de la sede.']);
            }

            if ($data['payer_type'] === 'coverage' && $coverageAmount !== $amount) {
                throw ValidationException::withMessages(['coverage_amount' => 'La cobertura debe asumir el total del cobro recurrente.']);
            }

            if ($data['payer_type'] === 'mixed' && ($coverageAmount <= 0 || $coverageAmount >= $amount)) {
                throw ValidationException::withMessages(['coverage_amount' => 'La cobertura debe asumir una parte del cobro recurrente.']);
            }

            return CoverageContract::create([...$data, 'legal_entity_id' => $location->legal_entity_id, 'created_by' => $actor->getKey(), 'coverage_amount' => $coverageAmount, 'next_charge_on' => $data['starts_on']]);
        }, 3);
    }

    public function generateDueCharges(?int $contractId = null): int
    {
        $query = CoverageContract::query()->with(['plan:id,name,billing_cycle', 'createdBy:id'])->where('status', 'active')->whereDate('next_charge_on', '<=', today());
        if ($contractId !== null) {
            $query->whereKey($contractId);
        }

        return $query->get()->sum(fn (CoverageContract $contract): int => $this->generateCharge($contract) ? 1 : 0);
    }

    private function generateCharge(CoverageContract $contract): bool
    {
        return DB::transaction(function () use ($contract): bool {
            $contract = CoverageContract::query()->with(['plan', 'createdBy'])->lockForUpdate()->findOrFail($contract->getKey());
            $today = today()->toDateString();
            if (! $contract->isChargeableOn($today) || CoverageContractCharge::query()->where('coverage_contract_id', $contract->getKey())->whereDate('due_date', $contract->next_charge_on)->exists()) {
                return false;
            }

            $actor = $contract->createdBy;
            if (! $actor instanceof User) {
                throw ValidationException::withMessages(['contract' => 'El contrato no tiene un responsable para emitir el cobro.']);
            }

            $invoice = $this->billing->issueInvoice([
                'location_id' => $contract->location_id, 'patient_id' => $contract->patient_id, 'payer_type' => $contract->payer_type,
                'coverage_name' => $contract->payer_type === 'patient' ? null : $contract->plan->name, 'coverage_amount' => $contract->coverage_amount, 'type' => 'invoice',
                'items' => [['description' => 'Plan '.$contract->plan->name.' · cobro recurrente', 'quantity' => 1, 'unit_price' => $contract->recurring_amount, 'tax_rate' => 0]],
            ], $actor);

            $contract->charges()->create(['invoice_id' => $invoice->getKey(), 'due_date' => $contract->next_charge_on, 'amount' => $contract->recurring_amount]);
            $contract->update(['next_charge_on' => $this->nextChargeDate($contract->next_charge_on, $contract->plan->billing_cycle)]);

            return true;
        }, 3);
    }

    private function nextChargeDate(CarbonInterface $date, string $cycle): string
    {
        return match ($cycle) {
            'quarterly' => $date->copy()->addMonthsNoOverflow(3)->toDateString(),
            'yearly' => $date->copy()->addYearNoOverflow()->toDateString(),
            default => $date->copy()->addMonthNoOverflow()->toDateString(),
        };
    }
}
