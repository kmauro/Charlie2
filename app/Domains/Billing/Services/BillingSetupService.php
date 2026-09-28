<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\Account;
use App\Domains\Billing\Models\FiscalConfiguration;
use App\Domains\Billing\Models\PaymentMethod;
use App\Domains\Organizations\Models\LegalEntity;

class BillingSetupService
{
    /** @param array{arca_certificate?: string|null, arca_private_key?: string|null} $arcaCredentials */
    public function initializeFor(LegalEntity $legalEntity, array $arcaCredentials = []): void
    {
        $configuration = FiscalConfiguration::firstOrCreate(['legal_entity_id' => $legalEntity->getKey()]);
        $credentials = array_filter($arcaCredentials, fn (?string $value): bool => filled($value));
        if ($credentials !== []) {
            $configuration->update($credentials);
        }

        foreach ([
            ['code' => '1.1.01', 'name' => 'Caja', 'type' => 'asset'],
            ['code' => '1.1.02', 'name' => 'Bancos y billeteras', 'type' => 'asset'],
            ['code' => '1.1.03', 'name' => 'Créditos por ventas', 'type' => 'asset'],
            ['code' => '2.1.01', 'name' => 'IVA débito fiscal', 'type' => 'liability'],
            ['code' => '4.1.01', 'name' => 'Ingresos por prestaciones', 'type' => 'income'],
            ['code' => '5.1.01', 'name' => 'Devoluciones sobre ventas', 'type' => 'expense'],
        ] as $account) {
            Account::firstOrCreate(
                ['legal_entity_id' => $legalEntity->getKey(), 'code' => $account['code']],
                [...$account, 'is_system' => true],
            );
        }

        foreach ([
            ['code' => 'cash', 'name' => 'Efectivo', 'kind' => 'cash', 'requires_reference' => false],
            ['code' => 'bank_transfer', 'name' => 'Transferencia', 'kind' => 'bank', 'requires_reference' => true],
            ['code' => 'mercado_pago_qr', 'name' => 'QR / Mercado Pago', 'kind' => 'wallet', 'requires_reference' => true],
        ] as $method) {
            PaymentMethod::firstOrCreate(
                ['legal_entity_id' => $legalEntity->getKey(), 'code' => $method['code']],
                $method,
            );
        }
    }
}
