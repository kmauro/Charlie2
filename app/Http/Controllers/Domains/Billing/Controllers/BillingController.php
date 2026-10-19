<?php

namespace App\Http\Controllers\Domains\Billing\Controllers;

use App\Domains\Billing\Models\Account;
use App\Domains\Billing\Models\CashClosure;
use App\Domains\Billing\Models\CashRegister;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\JournalEntry;
use App\Domains\Billing\Models\PaymentMethod;
use App\Domains\Billing\Models\Refund;
use App\Domains\Billing\Requests\CancelInvoiceRequest;
use App\Domains\Billing\Requests\CloseCashRegisterRequest;
use App\Domains\Billing\Requests\IssueCreditNoteRequest;
use App\Domains\Billing\Requests\OpenCashRegisterRequest;
use App\Domains\Billing\Requests\RegisterRefundRequest;
use App\Domains\Billing\Requests\StoreInvoiceRequest;
use App\Domains\Billing\Requests\StorePaymentRequest;
use App\Domains\Billing\Requests\StoreRefundRequest;
use App\Domains\Billing\Services\BillingService;
use App\Domains\Organizations\Models\Location;
use App\Domains\Patients\Models\Patient;
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

        $locations = Location::query()
            ->when($locationIds !== null, fn ($query) => $query->whereIn('id', $locationIds))
            ->orderBy('name')
            ->get(['id', 'name', 'legal_entity_id']);
        $selectedLocationId = (int) $request->session()->get(
            'selected_organization_location_id',
            0,
        );

        if ($locations->contains('id', $selectedLocationId)) {
            $locations = $locations->where('id', $selectedLocationId)->values();
        }

        $locationIds = $locations->modelKeys();
        $legalEntityIds = $locations->pluck('legal_entity_id')->unique();
        return Inertia::render('billing/views/Index', [
            'section' => $request->routeIs('accounting.*') ? 'accounting' : 'operations',
            'invoices' => Invoice::query()
                ->whereIn('location_id', $locationIds)
                ->latest('issued_at')
                ->limit(50)
                ->get(),
            'cashRegisters' => CashRegister::query()
                ->whereIn('location_id', $locationIds)
                ->get(),
            'locations' => $locations,
            'patients' => Patient::query()->active()->orderBy('last_name')->limit(200)->get(['id', 'first_name', 'last_name', 'coverage_name', 'member_number']),
            'paymentMethods' => PaymentMethod::query()->whereIn('legal_entity_id', $legalEntityIds)->where('is_active', true)->orderBy('name')->get(['id', 'legal_entity_id', 'name', 'kind', 'requires_reference']),
            'cashClosures' => CashClosure::query()
                ->whereIn('location_id', $locationIds)
                ->latest('closed_at')
                ->limit(20)
                ->get(),
            'refunds' => Refund::query()
                ->whereHas('invoice', fn ($query) => $query->whereIn('location_id', $locationIds))
                ->with('invoice:id,number')
                ->latest()
                ->limit(20)
                ->get(),
            'journalEntries' => Gate::forUser($actor)->allows('billing-accounting')
                ? JournalEntry::query()->whereIn('legal_entity_id', $legalEntityIds)->with(['lines.account'])->latest('entry_date')->limit(50)->get()
                : [],
            'accountBalances' => Gate::forUser($actor)->allows('billing-accounting')
                ? Account::query()
                    ->whereIn('legal_entity_id', $legalEntityIds)
                    ->leftJoin('journal_entry_lines', 'accounts.id', '=', 'journal_entry_lines.account_id')
                    ->select('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type')
                    ->selectRaw('COALESCE(SUM(journal_entry_lines.debit), 0) AS debits')
                    ->selectRaw('COALESCE(SUM(journal_entry_lines.credit), 0) AS credits')
                    ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type')
                    ->orderBy('accounts.code')
                    ->get()
                : [],
            'permissions' => [
                'canManageAccounting' => Gate::forUser($actor)->allows('billing-accounting'),
                'canIssueInvoices' => Gate::forUser($actor)->allows('billing-invoice'),
                'canRecordPayments' => Gate::forUser($actor)->allows('billing-payment'),
                'canOperateCash' => Gate::forUser($actor)->allows('billing-cash'),
                'canCancelInvoices' => Gate::forUser($actor)->allows('billing-full'),
            ],
        ]);
    }

    public function storeInvoice(StoreInvoiceRequest $request): RedirectResponse
    {
        $actor = $this->actor($request);
        $data = $request->validated();
        $this->ensureLocation((int) $data['location_id'], $actor);
        $this->billing->issueInvoice($data, $actor);

        return back()->with('success', 'La factura fue emitida y contabilizada.');
    }

    public function cancelInvoice(CancelInvoiceRequest $request, Invoice $invoice): RedirectResponse
    {
        $actor = $this->actor($request);
        $this->ensureLocation((int) $invoice->location_id, $actor);
        $this->billing->cancelInvoice($invoice, $request->validated('reason'), $actor);

        return back()->with('success', 'El comprobante fue anulado.');
    }

    public function storePayment(StorePaymentRequest $request): RedirectResponse
    {
        $actor = $this->actor($request);
        $data = $request->validated();
        $this->ensureLocation((int) Invoice::query()->findOrFail($data['invoice_id'])->location_id, $actor);
        $this->billing->recordPayment($data, $actor);

        return back()->with('success', 'El cobro fue registrado y contabilizado.');
    }

    public function storeCreditNote(IssueCreditNoteRequest $request, Invoice $invoice): RedirectResponse
    {
        $actor = $this->actor($request);
        $this->ensureLocation((int) $invoice->location_id, $actor);
        $data = $request->validated();
        $this->billing->issueCreditNote($invoice, (float) $data['amount'], $data['reason'], $actor);

        return back()->with('success', 'La nota de crédito fue emitida y contabilizada.');
    }

    public function storeRefund(StoreRefundRequest $request, Invoice $invoice): RedirectResponse
    {
        $actor = $this->actor($request);
        $this->ensureLocation((int) $invoice->location_id, $actor);
        $data = $request->validated();
        $this->billing->requestRefund($invoice, (float) $data['amount'], $data['reason'], $actor);

        return back()->with('success', 'La devolución quedó pendiente de autorización.');
    }

    public function openCashRegister(OpenCashRegisterRequest $request, Location $location): RedirectResponse
    {
        $actor = $this->actor($request);
        $this->ensureLocation((int) $location->getKey(), $actor);
        $this->billing->openCashRegister($location, (float) $request->validated('opening_amount'), $actor);

        return back()->with('success', 'La caja fue abierta.');
    }

    public function closeCashRegister(CloseCashRegisterRequest $request, Location $location): RedirectResponse
    {
        $actor = $this->actor($request);
        $this->ensureLocation((int) $location->getKey(), $actor);
        $this->billing->closeCashRegister($location, (float) $request->validated('counted_amount'), $actor);

        return back()->with('success', 'El arqueo y cierre fueron registrados.');
    }

    public function authorizeRefund(Request $request, Refund $refund): RedirectResponse
    {
        $actor = $this->actor($request);
        $this->ensureLocation((int) Invoice::query()->findOrFail($refund->invoice_id)->location_id, $actor);
        $this->billing->authorizeRefund($refund, $actor);

        return back()->with('success', 'La devolución fue autorizada.');
    }

    public function registerRefund(RegisterRefundRequest $request, Refund $refund): RedirectResponse
    {
        $actor = $this->actor($request);
        Gate::forUser($actor)->authorize('billing-full');
        $this->ensureLocation((int) Invoice::query()->findOrFail($refund->invoice_id)->location_id, $actor);
        $data = $request->validated();
        $this->billing->registerRefund($refund, $actor, (int) $data['payment_method_id'], $data['reference'] ?? null);

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
