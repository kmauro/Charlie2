<?php

namespace App\Jobs\Domains\Billing\Jobs;

use App\Domains\Billing\Models\FiscalDocument;
use App\Domains\Billing\Services\FiscalDocumentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AuthorizeFiscalDocument implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 3;
    public array $backoff = [10, 60, 300];
    public int $uniqueFor = 900;
    public function __construct(public int $fiscalDocumentId) {}
    public function uniqueId(): string { return (string) $this->fiscalDocumentId; }
    public function handle(FiscalDocumentService $fiscal): void { $fiscal->authorize(FiscalDocument::query()->findOrFail($this->fiscalDocumentId)); }
    public function failed(\Throwable $exception): void { FiscalDocument::query()->whereKey($this->fiscalDocumentId)->whereIn('status', ['pending', 'sending'])->update(['status' => 'uncertain', 'observations' => str($exception->getMessage())->limit(2000)]); }
}
