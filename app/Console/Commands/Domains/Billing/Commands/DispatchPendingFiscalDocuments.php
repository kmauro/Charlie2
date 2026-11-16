<?php

namespace App\Console\Commands\Domains\Billing\Commands;

use App\Domains\Billing\Models\FiscalDocument;
use App\Jobs\Domains\Billing\Jobs\AuthorizeFiscalDocument;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('billing:dispatch-fiscal-documents')]
#[Description('Reencola comprobantes fiscales pendientes de autorización o conciliación')]
class DispatchPendingFiscalDocuments extends Command
{
    public function handle(): int
    {
        FiscalDocument::query()->whereIn('status', ['pending', 'uncertain'])->orderBy('id')->chunkById(100, function ($documents): void {
            foreach ($documents as $document) AuthorizeFiscalDocument::dispatch((int) $document->getKey());
        });
        return self::SUCCESS;
    }
}
