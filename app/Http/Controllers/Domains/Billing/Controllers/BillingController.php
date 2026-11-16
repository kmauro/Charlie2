<?php

namespace App\Http\Controllers\Domains\Billing\Controllers;

use App\Domains\Billing\Models\CashClosure;
use App\Domains\Billing\Models\CashRegister;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\JournalEntry;
use App\Domains\Billing\Models\PaymentMethod;
use App\Domains\Billing\Models\Refund;
use App\Domains\Billing\Requests\CancelInvoiceRequest;
use App\Domains\Billing\Requests\ExportAccountingRequest;
use App\Domains\Billing\Requests\CloseCashRegisterRequest;
use App\Domains\Billing\Requests\IssueCreditNoteRequest;
use App\Domains\Billing\Requests\OpenCashRegisterRequest;
use App\Domains\Billing\Requests\RegisterRefundRequest;
use App\Domains\Billing\Requests\StoreInvoiceRequest;
use App\Domains\Billing\Requests\StorePaymentRequest;
use App\Domains\Billing\Requests\StoreRefundRequest;
use App\Domains\Billing\Services\BillingService;
use App\Domains\Billing\Services\AccountingLedgerQueryService;
use App\Domains\Audits\Data\AuditContext;
use App\Domains\Audits\Services\AuditService;
use App\Domains\Organizations\Models\Location;
use App\Domains\Patients\Models\Patient;
use App\Domains\Users\Models\User;
use App\Domains\Users\Services\UserScopeService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BillingController extends Controller
{
    public function __construct(
        private BillingService $billing,
        private UserScopeService $scope,
        private AccountingLedgerQueryService $ledger,
        private AuditService $audit,
    ) {}

    public function index(Request $request): Response
    {
        $actor = $this->actor($request);
        $this->authorizeOperator($actor, $request->routeIs('accounting.*'));
        $accessibleLocations = $this->ledger->locationsFor($actor);
        $locations = $accessibleLocations->values();
        $selectedLocationId = (int) $request->session()->get(
            'selected_organization_location_id',
            0,
        );

        if (! $request->routeIs('accounting.*') && $locations->contains('id', $selectedLocationId)) {
            $locations = $locations->where('id', $selectedLocationId)->values();
        }

        $locationIds = $locations->modelKeys();
        $legalEntityIds = $locations->pluck('legal_entity_id')->unique();
        $canManageAccounting = Gate::forUser($actor)->allows('billing-accounting');
        $accountingLegalEntityId = $accessibleLocations
            ->firstWhere('id', $selectedLocationId)?->legal_entity_id
            ?? $accessibleLocations->first()?->legal_entity_id;
        $accountingFilters = $accountingLegalEntityId === null ? null : [
            'legal_entity_id' => (int) $accountingLegalEntityId,
            'location_id' => $selectedLocationId > 0 && $accessibleLocations->contains('id', $selectedLocationId)
                ? $selectedLocationId
                : null,
            'from' => now()->subDays(30)->toDateString(),
            'to' => now()->toDateString(),
        ];

        return Inertia::render('billing/views/Index', [
            'section' => $request->routeIs('accounting.*') ? 'accounting' : 'operations',
            'invoices' => Invoice::query()
                ->whereIn('location_id', $locationIds)
                ->with('fiscalDocument:id,invoice_id,status,fiscal_number,cae,cae_expires_at,observations')
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
            'journalEntries' => $canManageAccounting && $accountingFilters !== null
                ? $this->ledger->entries($accountingFilters, $actor)->limit(50)->get()
                : [],
            'accountBalances' => $canManageAccounting && $accountingFilters !== null
                ? $this->ledger->balances($accountingFilters, $actor)
                : [],
            'accountingExport' => $canManageAccounting ? [
                'filters' => $accountingFilters,
                'locations' => $accessibleLocations,
                'legalEntities' => \App\Domains\Organizations\Models\LegalEntity::query()
                    ->whereIn('id', $accessibleLocations->pluck('legal_entity_id')->unique())
                    ->orderBy('business_name')
                    ->get(['id', 'business_name']),
                'url' => route('accounting.export'),
            ] : null,
            'permissions' => [
                'canManageAccounting' => $canManageAccounting,
                'canIssueInvoices' => Gate::forUser($actor)->allows('billing-invoice'),
                'canRecordPayments' => Gate::forUser($actor)->allows('billing-payment'),
                'canOperateCash' => Gate::forUser($actor)->allows('billing-cash'),
                'canCancelInvoices' => Gate::forUser($actor)->allows('billing-full'),
            ],
        ]);
    }

    public function exportAccountingData(ExportAccountingRequest $request): StreamedResponse
    {
        $actor = $this->actor($request);
        $filters = $request->validated();
        $this->ledger->exportLines($filters, $actor);
        $this->audit->recordResource(
            event: 'accounting.ledger_exported',
            auditableType: JournalEntry::class,
            afterValues: ['filters' => $filters],
            actor: $actor,
            context: AuditContext::fromRequest($request),
        );

        return response()->streamDownload(function () use ($filters, $actor): void {
            $stream = fopen('php://output', 'wb');
            if ($stream === false) {
                return;
            }

            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Fecha contable', 'Razón social', 'Sede', 'ID asiento', 'ID línea', 'Tipo origen', 'ID origen', 'Código cuenta', 'Nombre cuenta', 'Debe', 'Haber']);
            $this->ledger->exportLines($filters, $actor)->lazyById(500, 'journal_entry_lines.id', 'line_id')->each(function (object $line) use ($stream): void {
                fputcsv($stream, array_map(fn (string|int|float|null $value): string => $this->safeCsvValue($value === null ? '' : (string) $value), [
                    $line->entry_date,
                    $line->legal_entity_name,
                    $line->location_name,
                    $line->journal_entry_id,
                    $line->line_id,
                    class_basename($line->source_type),
                    $line->source_id,
                    $line->account_code,
                    $line->account_name,
                    number_format((float) $line->debit, 2, '.', ''),
                    number_format((float) $line->credit, 2, '.', ''),
                ]));
            });
            fclose($stream);
        }, 'libro-diario-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
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

    private function authorizeOperator(User $actor, bool $accounting): void
    {
        Gate::forUser($actor)->authorize($accounting ? 'billing-accounting' : 'billing-operate');
    }

    private function ensureLocation(int $locationId, User $actor): void
    {
        abort_unless($this->scope->canAccessLocation($actor, $locationId), 403);
    }

    private function safeCsvValue(string $value): string
    {
        return Str::startsWith($value, ['=', '+', '-', '@']) ? "'$value" : $value;
    }
}
