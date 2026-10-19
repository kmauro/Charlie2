<?php

namespace App\Domains\Billing\Data;

use Carbon\CarbonImmutable;

final readonly class CashMovement
{
    /** @param 'in'|'out' $direction */
    public function __construct(
        public int $cashRegisterId,
        public int $amountCents,
        public string $direction,
        public string $sourceType,
        public int $sourceId,
        public CarbonImmutable $occurredAt,
    ) {}
}
