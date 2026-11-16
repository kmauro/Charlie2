<?php

namespace App\Domains\Billing\Services;

use App\Domains\Audits\Services\AuditService;
use App\Domains\Billing\Contracts\FiscalAuthorizationService;
use App\Domains\Billing\Data\JournalLine;
use App\Domains\Billing\Enums\FiscalDocumentStatus;
use App\Domains\Billing\Models\FiscalConfiguration;
use App\Domains\Billing\Models\FiscalDocument;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\JournalEntry;
use App\Domains\Billing\Contracts\JournalPostingService;
use App\Domains\Organizations\Models\Location;
use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FiscalDocumentService
{
    public function __construct(private FiscalAuthorizationService $provider, private JournalPostingService $journal, private AuditService $audit) {}

    public function shouldAuthorize(Location $location): bool
    {
        return $location->arca_point_of_sale !== null && FiscalConfiguration::query()
            ->where('legal_entity_id', $location->legal_entity_id)->where('arca_enabled', true)->exists();
    }

    public function create(Invoice $invoice, Location $location, User $actor): FiscalDocument
    {
        $configuration = FiscalConfiguration::query()->where('legal_entity_id', $location->legal_entity_id)->firstOrFail();
        if (! $configuration->arca_enabled || $location->arca_point_of_sale === null) throw ValidationException::withMessages(['location_id' => 'La sede no tiene un emisor ARCA habilitado.']);
        $documentType = $invoice->type === 'credit_note' ? 3 : 1;
        $snapshot = ['document_type' => $documentType, 'currency' => $invoice->currency, 'net_amount' => $invoice->net_amount, 'tax_amount' => $invoice->tax_amount, 'total_amount' => $invoice->total_amount, 'concept' => 'Prestación'];
        return FiscalDocument::query()->create(['invoice_id' => $invoice->getKey(), 'location_id' => $location->getKey(), 'requested_by' => $actor->getKey(), 'environment' => (string) $location->legalEntity->arca_environment, 'issuer_cuit' => preg_replace('/\D+/', '', (string) $location->legalEntity->cuit), 'point_of_sale' => $location->arca_point_of_sale, 'document_type' => $documentType, 'fiscal_date' => now()->toDateString(), 'request_snapshot' => $snapshot, 'request_hash' => hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR)), 'idempotency_key' => (string) Str::uuid()]);
    }

    public function authorize(FiscalDocument $document): FiscalDocument
    {
        $sending = DB::transaction(function () use ($document): FiscalDocument {
            $locked = FiscalDocument::query()->with(['invoice', 'requestedBy'])->lockForUpdate()->findOrFail($document->getKey());
            if ($locked->status === FiscalDocumentStatus::Authorized) return $locked;
            if ($locked->status === FiscalDocumentStatus::Rejected) return $locked;
            $series = FiscalDocument::query()->where('environment', $locked->environment)->where('issuer_cuit', $locked->issuer_cuit)->where('point_of_sale', $locked->point_of_sale)->where('document_type', $locked->document_type)->whereNotNull('fiscal_number')->lockForUpdate()->get();
            $candidate = (int) ($series->max('fiscal_number') ?? $this->provider->lastAuthorizedNumber($locked));
            $locked->update(['status' => FiscalDocumentStatus::Sending, 'fiscal_number' => $locked->fiscal_number ?? $candidate + 1, 'sent_at' => now()]);
            return $locked->refresh();
        }, 3);
        if ($sending->status === FiscalDocumentStatus::Authorized || $sending->status === FiscalDocumentStatus::Rejected) return $sending;
        try { $result = $this->provider->authorize($sending); } catch (\Throwable $exception) { return $this->uncertain($sending, $exception->getMessage()); }
        if (! $result->authorized) return $this->reject($sending, $result->code, $result->message);
        return DB::transaction(function () use ($sending, $result): FiscalDocument {
            $locked = FiscalDocument::query()->with(['invoice', 'requestedBy'])->lockForUpdate()->findOrFail($sending->getKey());
            if ($locked->status === FiscalDocumentStatus::Authorized) return $locked;
            $locked->update(['status' => FiscalDocumentStatus::Authorized, 'cae' => $result->cae, 'cae_expires_at' => $result->expiresAt, 'observations' => $result->message, 'authorized_at' => now()]);
            $locked->attempts()->create(['status' => FiscalDocumentStatus::Authorized->value, 'fiscal_number' => $locked->fiscal_number, 'provider_code' => $result->code, 'message' => $result->message]);
            $invoice = $locked->invoice;
            $invoice->update(['status' => 'issued']);
            if (! JournalEntry::query()->where('source_type', Invoice::class)->where('source_id', $invoice->getKey())->exists()) $this->postInvoiceEntry($invoice, $locked->requestedBy);
            $this->audit->record('fiscal_document.authorized', $locked, actor: $locked->requestedBy);
            return $locked->refresh();
        }, 3);
    }

    private function reject(FiscalDocument $document, ?string $code, ?string $message): FiscalDocument { $candidate = $document->fiscal_number; $document->update(['status' => FiscalDocumentStatus::Rejected, 'fiscal_number' => null, 'observations' => $message]); $document->attempts()->create(['status' => FiscalDocumentStatus::Rejected->value, 'fiscal_number' => $candidate, 'provider_code' => $code, 'message' => $message]); return $document->refresh(); }
    private function uncertain(FiscalDocument $document, string $message): FiscalDocument { $document->update(['status' => FiscalDocumentStatus::Uncertain, 'observations' => Str::limit($message, 2000)]); $document->attempts()->create(['status' => FiscalDocumentStatus::Uncertain->value, 'fiscal_number' => $document->fiscal_number, 'message' => Str::limit($message, 2000)]); return $document->refresh(); }
    private function postInvoiceEntry(Invoice $invoice, User $actor): void { $this->journal->post($invoice, (int) $invoice->legal_entity_id, $actor, ($invoice->type === 'credit_note' ? 'Nota de crédito ' : 'Emisión ').$invoice->number, $invoice->type === 'credit_note' ? [new JournalLine('4.1.01', (int) round($invoice->net_amount * 100), 0), new JournalLine('1.1.03', 0, (int) round($invoice->total_amount * 100))] : [new JournalLine('1.1.03', (int) round($invoice->total_amount * 100), 0), new JournalLine('4.1.01', 0, (int) round($invoice->net_amount * 100)), new JournalLine('2.1.01', 0, (int) round($invoice->tax_amount * 100))]); }
}
