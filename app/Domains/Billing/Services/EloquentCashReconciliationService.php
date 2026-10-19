<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Contracts\CashReconciliationService;
use App\Domains\Billing\Data\CashMovement;
use App\Domains\Billing\Models\CashRegister;
use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\Refund;
use Carbon\CarbonImmutable;

class EloquentCashReconciliationService implements CashReconciliationService
{
    public function movementsForOpenPeriod(CashRegister $register): array
    {
        if (! $register->isOpen()) {
            return [];
        }

        $cashRegisterId = (int) $register->getKey();
        $openedAt = $register->opened_at;

        $payments = Payment::query()
            ->where('cash_register_id', $cashRegisterId)
            ->where('paid_at', '>=', $openedAt)
            ->get()
            ->map(fn (Payment $payment): CashMovement => new CashMovement(
                cashRegisterId: $cashRegisterId,
                amountCents: $this->toCents($payment->amount),
                direction: 'in',
                sourceType: $payment::class,
                sourceId: (int) $payment->getKey(),
                occurredAt: CarbonImmutable::instance($payment->paid_at),
            ));
        $refunds = Refund::query()
            ->where('cash_register_id', $cashRegisterId)
            ->where('status', 'registered')
            ->where('registered_at', '>=', $openedAt)
            ->get()
            ->map(fn (Refund $refund): CashMovement => new CashMovement(
                cashRegisterId: $cashRegisterId,
                amountCents: $this->toCents($refund->amount),
                direction: 'out',
                sourceType: $refund::class,
                sourceId: (int) $refund->getKey(),
                occurredAt: CarbonImmutable::instance($refund->registered_at),
            ));

        return $payments->concat($refunds)->sortBy('occurredAt')->values()->all();
    }

    public function expectedAmountCents(CashRegister $register): int
    {
        $openingAmountCents = $this->toCents($register->opening_amount);

        return $openingAmountCents + collect($this->movementsForOpenPeriod($register))
            ->sum(fn (CashMovement $movement): int => $movement->direction === 'in'
                ? $movement->amountCents
                : -$movement->amountCents);
    }

    private function toCents(mixed $amount): int
    {
        return (int) round((float) $amount * 100);
    }
}
