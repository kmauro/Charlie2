<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\ExternalFinancialMovement;
use App\Domains\Billing\Models\FinancialAccount;
use App\Domains\Billing\Models\FinancialStatementImport;
use App\Domains\Users\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FinancialStatementImportService
{
    public function import(FinancialAccount $account, UploadedFile $file, User $actor): FinancialStatementImport
    {
        $contents = $file->get();
        $hash = hash('sha256', $contents);

        if (trim($contents) === '') {
            throw ValidationException::withMessages(['file' => 'El CSV no contiene movimientos.']);
        }

        $lines = preg_split('/\r\n|\r|\n/', trim($contents)) ?: [];
        $rows = array_map(fn (string $line): array => str_getcsv($line, ',', '"', ''), $lines);
        $header = array_map(fn (string $value): string => Str::lower(trim($value)), array_shift($rows) ?? []);

        if (array_diff(['fecha', 'importe'], $header) !== []) {
            throw ValidationException::withMessages(['file' => 'El CSV debe incluir las columnas fecha e importe.']);
        }

        $index = array_flip($header);
        $movements = [];
        $dates = [];

        foreach ($rows as $offset => $row) {
            $lineNumber = $offset + 2;

            if (count($row) !== count($header)) {
                throw ValidationException::withMessages(['file' => "La fila {$lineNumber} no tiene la cantidad de columnas esperada."]);
            }

            $date = $row[$index['fecha']];
            $amount = str_replace(',', '.', $row[$index['importe']]);

            if (! is_numeric($amount)) {
                throw ValidationException::withMessages(['file' => "El importe de la fila {$lineNumber} no es numérico."]);
            }

            if (strtotime($date) === false) {
                throw ValidationException::withMessages(['file' => "La fecha de la fila {$lineNumber} no es válida."]);
            }

            $dates[] = $date;
            $reference = isset($index['referencia']) ? ($row[$index['referencia']] ?: null) : null;
            $movements[] = [
                'financial_account_id' => $account->getKey(),
                'external_id' => isset($index['id']) ? ($row[$index['id']] ?: null) : null,
                'operated_at' => $date,
                'settled_at' => isset($index['fecha_acreditacion']) ? ($row[$index['fecha_acreditacion']] ?: null) : null,
                'amount' => $amount,
                'currency' => isset($index['moneda']) ? ($row[$index['moneda']] ?: $account->currency) : $account->currency,
                'reference' => $reference,
                'normalized_reference' => $reference !== null ? Str::upper(Str::replaceMatches('/[^A-Za-z0-9]/', '', $reference)) : null,
                'description' => isset($index['descripcion']) ? ($row[$index['descripcion']] ?: null) : null,
                'source_row' => $lineNumber,
            ];
        }

        if ($movements === []) {
            throw ValidationException::withMessages(['file' => 'El CSV no contiene movimientos.']);
        }

        return DB::transaction(function () use ($account, $file, $actor, $hash, $movements, $dates): FinancialStatementImport {
            $existing = FinancialStatementImport::query()
                ->where('financial_account_id', $account->getKey())
                ->where('file_hash', $hash)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $import = FinancialStatementImport::create([
                'financial_account_id' => $account->getKey(),
                'imported_by' => $actor->getKey(),
                'file_name' => $file->getClientOriginalName(),
                'file_hash' => $hash,
                'period_from' => min($dates),
                'period_to' => max($dates),
                'row_count' => count($movements),
            ]);

            foreach ($movements as $movement) {
                ExternalFinancialMovement::create([
                    ...$movement,
                    'financial_statement_import_id' => $import->getKey(),
                ]);
            }

            return $import;
        }, attempts: 3);
    }
}
