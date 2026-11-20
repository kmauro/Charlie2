<?php

use App\Domains\Billing\Models\Account;
use App\Domains\Billing\Models\ExternalFinancialMovement;
use App\Domains\Billing\Models\FinancialAccount;
use App\Domains\Billing\Models\FinancialStatementImport;
use App\Domains\Billing\Services\FinancialStatementImportService;
use App\Domains\Organizations\Models\LegalEntity;
use App\Domains\Users\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

it('rejects invalid statements without creating a batch or movements', function (string $csv, string $message): void {
    [$account, $actor] = statementImportContext();
    $file = UploadedFile::fake()->createWithContent('extracto.csv', $csv);

    expect(fn () => app(FinancialStatementImportService::class)->import($account, $file, $actor))
        ->toThrow(ValidationException::class, $message);

    expect(FinancialStatementImport::query()->count())->toBe(0)
        ->and(ExternalFinancialMovement::query()->count())->toBe(0);
})->with([
    'importe no numérico' => ["fecha,importe\n2026-11-20,importe-inválido", 'El importe de la fila 2 no es numérico.'],
    'solo encabezado' => ['fecha,importe', 'El CSV no contiene movimientos.'],
    'fila inválida después de una válida' => ["fecha,importe\n2026-11-20,120.50\n2026-11-21,importe-inválido", 'El importe de la fila 3 no es numérico.'],
]);

it('imports valid rows once with their original row numbers', function (): void {
    [$account, $actor] = statementImportContext();
    $file = UploadedFile::fake()->createWithContent('extracto.csv', "fecha,importe,referencia\n2026-11-20,120.50,COBRO-1\n2026-11-21,-10.25,DEVOLUCION-1");
    $service = app(FinancialStatementImportService::class);

    $import = $service->import($account, $file, $actor);
    $repeatedImport = $service->import($account, $file, $actor);

    expect($import->row_count)->toBe(2)
        ->and($repeatedImport->getKey())->toBe($import->getKey())
        ->and(FinancialStatementImport::query()->count())->toBe(1)
        ->and(ExternalFinancialMovement::query()->orderBy('source_row')->pluck('source_row')->all())->toBe([2, 3]);
});

it('rejects an invalid file even when a previous zero-row batch has the same hash', function (): void {
    [$account, $actor] = statementImportContext();
    $csv = "fecha,importe\n2026-11-20,importe-inválido";
    $file = UploadedFile::fake()->createWithContent('extracto.csv', $csv);
    FinancialStatementImport::query()->create([
        'financial_account_id' => $account->getKey(),
        'imported_by' => $actor->getKey(),
        'file_name' => 'extracto.csv',
        'file_hash' => hash('sha256', $csv),
        'period_from' => '2026-11-20',
        'period_to' => '2026-11-20',
        'row_count' => 0,
    ]);

    expect(fn () => app(FinancialStatementImportService::class)->import($account, $file, $actor))
        ->toThrow(ValidationException::class, 'El importe de la fila 2 no es numérico.');

    expect(FinancialStatementImport::query()->count())->toBe(1)
        ->and(ExternalFinancialMovement::query()->count())->toBe(0);
});

/** @return array{FinancialAccount, User} */
function statementImportContext(): array
{
    $entity = LegalEntity::factory()->create();
    $account = Account::query()->create([
        'legal_entity_id' => $entity->getKey(),
        'code' => '1.1.50',
        'name' => 'Banco de prueba',
        'type' => 'asset',
    ]);
    $financialAccount = FinancialAccount::query()->create([
        'legal_entity_id' => $entity->getKey(),
        'account_id' => $account->getKey(),
        'name' => 'Cuenta de prueba',
        'provider' => 'Banco de prueba',
        'masked_identifier' => '****1234',
        'currency' => 'ARS',
    ]);

    return [$financialAccount, User::factory()->create()];
}
