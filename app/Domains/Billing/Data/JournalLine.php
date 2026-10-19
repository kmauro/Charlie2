<?php

namespace App\Domains\Billing\Data;

final readonly class JournalLine
{
    public function __construct(
        public string $accountCode,
        public int $debitCents,
        public int $creditCents,
    ) {}
}
