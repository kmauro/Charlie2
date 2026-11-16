<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\Account;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\JournalEntry;
use App\Domains\Billing\Models\JournalEntryLine;
use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\Refund;
use App\Domains\Organizations\Models\LegalEntity;
use App\Domains\Organizations\Models\Location;
use App\Domains\Users\Models\User;
use App\Domains\Users\Services\UserScopeService;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AccountingLedgerQueryService
{
    public function __construct(private UserScopeService $scope) {}

    /** @param array{legal_entity_id: int, location_id?: int|null, from?: string|null, to?: string|null} $filters */
    public function exportLines(array $filters, User $actor): Builder
    {
        $locationIds = $this->authorizedLocationIds($filters, $actor);
        $lines = new JournalEntryLine;
        $entries = new JournalEntry;
        $accounts = new Account;
        $entities = new LegalEntity;
        $invoices = new Invoice;
        $payments = new Payment;
        $refunds = new Refund;

        return $lines->newQuery()->toBase()
            ->join($entries->getTable().' as journal_entries', 'journal_entries.id', '=', $lines->getTable().'.journal_entry_id')
            ->join($accounts->getTable().' as accounts', 'accounts.id', '=', $lines->getTable().'.account_id')
            ->join($entities->getTable().' as legal_entities', 'legal_entities.id', '=', 'journal_entries.legal_entity_id')
            ->leftJoin($invoices->getTable().' as invoice_sources', function ($join) use ($invoices): void {
                $join->on('invoice_sources.id', '=', 'journal_entries.source_id')
                    ->where('journal_entries.source_type', Invoice::class);
            })
            ->leftJoin($payments->getTable().' as payment_sources', function ($join): void {
                $join->on('payment_sources.id', '=', 'journal_entries.source_id')
                    ->where('journal_entries.source_type', Payment::class);
            })
            ->leftJoin($invoices->getTable().' as payment_invoices', 'payment_invoices.id', '=', 'payment_sources.invoice_id')
            ->leftJoin($refunds->getTable().' as refund_sources', function ($join): void {
                $join->on('refund_sources.id', '=', 'journal_entries.source_id')
                    ->where('journal_entries.source_type', Refund::class);
            })
            ->leftJoin($invoices->getTable().' as refund_invoices', 'refund_invoices.id', '=', 'refund_sources.invoice_id')
            ->where('journal_entries.legal_entity_id', $filters['legal_entity_id'])
            ->whereColumn('accounts.legal_entity_id', 'journal_entries.legal_entity_id')
            ->when($filters['from'] ?? null, fn (Builder $query, string $from): Builder => $query->whereDate('journal_entries.entry_date', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to): Builder => $query->whereDate('journal_entries.entry_date', '<=', $to))
            ->where(function (Builder $query) use ($locationIds): void {
                $query->where(function (Builder $query) use ($locationIds): void {
                    $query->where('journal_entries.source_type', Invoice::class)
                        ->whereColumn('invoice_sources.legal_entity_id', 'journal_entries.legal_entity_id')
                        ->whereIn('invoice_sources.location_id', $locationIds);
                })->orWhere(function (Builder $query) use ($locationIds): void {
                    $query->where('journal_entries.source_type', Payment::class)
                        ->whereColumn('payment_invoices.legal_entity_id', 'journal_entries.legal_entity_id')
                        ->whereIn('payment_invoices.location_id', $locationIds);
                })->orWhere(function (Builder $query) use ($locationIds): void {
                    $query->where('journal_entries.source_type', Refund::class)
                        ->whereColumn('refund_invoices.legal_entity_id', 'journal_entries.legal_entity_id')
                        ->whereIn('refund_invoices.location_id', $locationIds);
                });
            })
            ->select([
                $lines->getTable().'.id as line_id',
                'journal_entries.id as journal_entry_id',
                'journal_entries.entry_date',
                'journal_entries.source_type',
                'journal_entries.source_id',
                'legal_entities.business_name as legal_entity_name',
                'accounts.code as account_code',
                'accounts.name as account_name',
                $lines->getTable().'.debit',
                $lines->getTable().'.credit',
            ])
            ->selectRaw('COALESCE(invoice_sources.location_id, payment_invoices.location_id, refund_invoices.location_id) as location_id')
            ->selectRaw('COALESCE(invoice_locations.name, payment_locations.name, refund_locations.name) as location_name')
            ->leftJoin((new Location)->getTable().' as invoice_locations', 'invoice_locations.id', '=', 'invoice_sources.location_id')
            ->leftJoin((new Location)->getTable().' as payment_locations', 'payment_locations.id', '=', 'payment_invoices.location_id')
            ->leftJoin((new Location)->getTable().' as refund_locations', 'refund_locations.id', '=', 'refund_invoices.location_id')
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_entries.id')
            ->orderBy($lines->getTable().'.id');
    }

    /** @param array{legal_entity_id: int, location_id?: int|null, from?: string|null, to?: string|null} $filters */
    public function entries(array $filters, User $actor): EloquentBuilder
    {
        $entryIds = $this->exportLines($filters, $actor)->select('journal_entries.id');

        return JournalEntry::query()
            ->whereIn('id', $entryIds)
            ->with(['lines.account'])
            ->orderByDesc('entry_date')
            ->orderByDesc('id');
    }

    /** @param array{legal_entity_id: int, location_id?: int|null, from?: string|null, to?: string|null} $filters */
    public function balances(array $filters, User $actor): Collection
    {
        $lineIds = $this->exportLines($filters, $actor)->select('journal_entry_lines.id');

        return Account::query()
            ->where('legal_entity_id', $filters['legal_entity_id'])
            ->leftJoin('journal_entry_lines', 'accounts.id', '=', 'journal_entry_lines.account_id')
            ->whereIn('journal_entry_lines.id', $lineIds)
            ->select('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type')
            ->selectRaw('COALESCE(SUM(journal_entry_lines.debit), 0) AS debits')
            ->selectRaw('COALESCE(SUM(journal_entry_lines.credit), 0) AS credits')
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type')
            ->orderBy('accounts.code')
            ->get();
    }

    /** @return Collection<int, Location> */
    public function locationsFor(User $actor): Collection
    {
        $locationIds = $this->scope->locationIdsFor($actor);

        return Location::query()
            ->when($locationIds !== null, fn (EloquentBuilder $query) => $query->whereIn('id', $locationIds))
            ->orderBy('name')
            ->get(['id', 'name', 'legal_entity_id']);
    }

    /** @param array{legal_entity_id: int, location_id?: int|null} $filters
     * @return array<int, int>
     */
    private function authorizedLocationIds(array $filters, User $actor): array
    {
        $allowed = $this->scope->locationIdsFor($actor);
        $locations = Location::query()
            ->where('legal_entity_id', $filters['legal_entity_id'])
            ->when($allowed !== null, fn (EloquentBuilder $query) => $query->whereIn('id', $allowed));

        if (isset($filters['location_id']) && $filters['location_id'] !== null) {
            $location = $locations->whereKey($filters['location_id'])->first();
            if ($location === null) {
                throw new AccessDeniedHttpException;
            }

            return [(int) $location->getKey()];
        }

        if ($allowed !== null && ! $this->scope->canAccessLegalEntity($actor, $filters['legal_entity_id'])) {
            throw new AccessDeniedHttpException;
        }

        return $locations->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
    }
}
