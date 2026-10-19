<?php

namespace App\Http\Controllers\Domains\Coverages\Controllers;

use App\Domains\Coverages\Models\CoverageContract;
use App\Domains\Coverages\Models\CoveragePlan;
use App\Domains\Coverages\Services\CoverageService;
use App\Domains\Organizations\Models\Location;
use App\Domains\Patients\Models\Patient;
use App\Domains\Users\Models\User;
use App\Domains\Users\Services\UserScopeService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Domains\Coverages\Requests\StoreCoverageContractRequest;
use App\Http\Requests\Domains\Coverages\Requests\StoreCoveragePlanBenefitRequest;
use App\Http\Requests\Domains\Coverages\Requests\StoreCoveragePlanRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CoverageController extends Controller
{
    public function __construct(private CoverageService $coverage, private UserScopeService $scope) {}

    public function index(Request $request): Response
    {
        $actor = $this->actor($request);
        Gate::forUser($actor)->authorize('billing-operate');
        $locationIds = $this->scope->locationIdsFor($actor);
        $locations = Location::query()->when($locationIds !== null, fn ($query) => $query->whereIn('id', $locationIds))->orderBy('name')->get(['id', 'name', 'legal_entity_id']);
        $selectedLocationId = (int) $request->session()->get('selected_organization_location_id', 0);
        if ($locations->contains('id', $selectedLocationId)) {
            $locations = $locations->where('id', $selectedLocationId)->values();
        }
        $accessibleLocationIds = $locations->modelKeys();
        $legalEntityIds = $locations->pluck('legal_entity_id')->unique();

        return Inertia::render('coverages/views/Index', [
            'locations' => $locations,
            'patients' => Patient::query()->active()->orderBy('last_name')->limit(200)->get(['id', 'first_name', 'last_name']),
            'plans' => CoveragePlan::query()->whereIn('legal_entity_id', $legalEntityIds)->with('benefits')->orderBy('name')->get(),
            'contracts' => CoverageContract::query()->whereIn('location_id', $accessibleLocationIds)->with(['patient:id,first_name,last_name', 'plan:id,name,billing_cycle', 'charges.invoice:id,number,status'])->latest()->limit(50)->get(),
            'permissions' => ['canManage' => Gate::forUser($actor)->allows('billing-full')],
        ]);
    }

    public function storePlan(StoreCoveragePlanRequest $request): RedirectResponse
    {
        $actor = $this->actor($request);
        $this->ensureLegalEntity((int) $request->validated('legal_entity_id'), $actor);
        $this->coverage->createPlan($request->validated());

        return back()->with('success', 'El plan de cobertura fue creado.');
    }

    public function storeBenefit(StoreCoveragePlanBenefitRequest $request, CoveragePlan $plan): RedirectResponse
    {
        $actor = $this->actor($request);
        $this->ensureLegalEntity((int) $plan->legal_entity_id, $actor);
        $this->coverage->addBenefit($plan, $request->validated());

        return back()->with('success', 'La prestación fue agregada al plan.');
    }

    public function storeContract(StoreCoverageContractRequest $request): RedirectResponse
    {
        $actor = $this->actor($request);
        $data = $request->validated();
        $this->ensureLocation((int) $data['location_id'], $actor);
        $this->coverage->createContract($data, $actor);

        return back()->with('success', 'El contrato fue creado y quedó listo para facturar.');
    }

    public function generateCharges(Request $request, CoverageContract $contract): RedirectResponse
    {
        $actor = $this->actor($request);
        Gate::forUser($actor)->authorize('billing-full');
        $this->ensureLocation((int) $contract->location_id, $actor);
        $generated = $this->coverage->generateDueCharges((int) $contract->getKey());

        return back()->with('success', $generated === 1 ? 'El cobro recurrente fue emitido.' : 'El contrato no tiene un cobro pendiente para emitir.');
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }

    private function ensureLocation(int $locationId, User $actor): void
    {
        abort_unless($this->scope->canAccessLocation($actor, $locationId), 403);
    }

    private function ensureLegalEntity(int $legalEntityId, User $actor): void
    {
        $allowedLocations = $this->scope->locationIdsFor($actor);
        if ($allowedLocations === null) {
            return;
        }
        abort_unless(Location::query()->whereKey($allowedLocations)->where('legal_entity_id', $legalEntityId)->exists(), 403);
    }
}
