<?php

namespace App\Http\Controllers\Domains\Billing\Controllers;

use App\Domains\Billing\Models\CashRegister;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Refund;
use App\Domains\Billing\Services\BillingService;
use App\Domains\Organizations\Models\Location;
use App\Domains\Users\Models\User;
use App\Domains\Users\Services\UserScopeService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    public function __construct(private BillingService $billing, private UserScopeService $scope) {}

    public function index(Request $request): Response
    {
        $actor = $this->actor($request);
        $this->authorizeOperator($actor);
        $locationIds = $this->scope->locationIdsFor($actor);

        return Inertia::render('billing/views/Index', [
            'invoices' => Invoice::query()
                ->when($locationIds !== null, fn ($query) => $query->whereIn('location_id', $locationIds))
                ->latest('issued_at')
                ->limit(50)
                ->get(),
            'cashRegisters' => CashRegister::query()
                ->when($locationIds !== null, fn ($query) => $query->whereIn('location_id', $locationIds))
                ->get(),
        ]);
    }

    public function storeInvoice(Request $request): RedirectResponse
    {
        $actor = $this->actor($request);
        $this->authorizeOperator($actor);
        $data = $request->validate(['location_id' => ['required', 'integer', 'exists:locations,id'], 'patient_id' => ['nullable', 'integer', 'exists:patients,id'], 'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'], 'type' => ['required', 'in:invoice,advance'], 'items' => ['required', 'array', 'min:1'], 'items.*.description' => ['required', 'string', 'max:255'], 'items.*.quantity' => ['required', 'numeric', 'gt:0'], 'items.*.unit_price' => ['required', 'numeric', 'gte:0'], 'items.*.tax_rate' => ['nullable', 'numeric', 'between:0,100'], 'installments' => ['nullable', 'array'], 'installments.*.amount' => ['required', 'numeric', 'gt:0'], 'installments.*.due_date' => ['required', 'date']]);
        $this->ensureLocation((int) $data['location_id'], $actor);
        $this->billing->issueInvoice($data, $actor);

        return back()->with('success', 'La factura fue emitida y contabilizada.');
    }

    public function storePayment(Request $request): RedirectResponse
    {
        $actor = $this->actor($request);
        $this->authorizeOperator($actor);
        $data = $request->validate(['invoice_id' => ['required', 'integer', 'exists:invoices,id'], 'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'], 'amount' => ['required', 'numeric', 'gt:0'], 'reference' => ['nullable', 'string', 'max:120']]);
        $this->ensureLocation((int) Invoice::query()->findOrFail($data['invoice_id'])->location_id, $actor);
        $this->billing->recordPayment($data, $actor);

        return back()->with('success', 'El cobro fue registrado y contabilizado.');
    }

    public function storeCreditNote(Request $request, Invoice $invoice): RedirectResponse
    {
        $actor = $this->actor($request);
        $this->authorizeOperator($actor);
        $this->ensureLocation((int) $invoice->location_id, $actor);
        $data = $request->validate(['amount' => ['required', 'numeric', 'gt:0'], 'reason' => ['required', 'string', 'max:500']]);
        $this->billing->issueCreditNote($invoice, (float) $data['amount'], $data['reason'], $actor);

        return back()->with('success', 'La nota de crédito fue emitida y contabilizada.');
    }

    public function storeRefund(Request $request, Invoice $invoice): RedirectResponse
    {
        $actor = $this->actor($request);
        $this->authorizeOperator($actor);
        $this->ensureLocation((int) $invoice->location_id, $actor);
        $data = $request->validate(['amount' => ['required', 'numeric', 'gt:0'], 'reason' => ['required', 'string', 'max:500']]);
        $this->billing->requestRefund($invoice, (float) $data['amount'], $data['reason'], $actor);

        return back()->with('success', 'La devolución quedó pendiente de autorización.');
    }

    public function openCashRegister(Request $request, Location $location): RedirectResponse
    {
        $actor = $this->actor($request);
        $this->authorizeOperator($actor);
        $this->ensureLocation((int) $location->getKey(), $actor);
        $data = $request->validate(['opening_amount' => ['required', 'numeric', 'gte:0']]);
        $this->billing->openCashRegister($location, (float) $data['opening_amount'], $actor);

        return back()->with('success', 'La caja fue abierta.');
    }

    public function closeCashRegister(Request $request, Location $location): RedirectResponse
    {
        $actor = $this->actor($request);
        $this->authorizeOperator($actor);
        $this->ensureLocation((int) $location->getKey(), $actor);
        $data = $request->validate(['counted_amount' => ['required', 'numeric', 'gte:0']]);
        $this->billing->closeCashRegister($location, (float) $data['counted_amount'], $actor);

        return back()->with('success', 'El arqueo y cierre fueron registrados.');
    }

    public function authorizeRefund(Request $request, Refund $refund): RedirectResponse
    {
        $actor = $this->actor($request);
        $this->ensureLocation((int) Invoice::query()->findOrFail($refund->invoice_id)->location_id, $actor);
        $this->billing->authorizeRefund($refund, $actor);

        return back()->with('success', 'La devolución fue autorizada.');
    }

    public function registerRefund(Request $request, Refund $refund): RedirectResponse
    {
        $actor = $this->actor($request);
        $this->authorizeOperator($actor);
        $this->ensureLocation((int) Invoice::query()->findOrFail($refund->invoice_id)->location_id, $actor);
        $this->billing->registerRefund($refund, $actor);

        return back()->with('success', 'La devolución externa fue registrada y contabilizada.');
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }

    private function authorizeOperator(User $actor): void
    {
        Gate::forUser($actor)->authorize('billing-operate');
    }

    private function ensureLocation(int $locationId, User $actor): void
    {
        abort_unless($this->scope->canAccessLocation($actor, $locationId), 403);
    }
}
