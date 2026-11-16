<?php

namespace App\Domains\Billing\Data;

final readonly class FiscalAuthorizationResult
{
    public function __construct(public bool $authorized, public ?string $cae = null, public ?string $expiresAt = null, public ?string $code = null, public ?string $message = null) {}
}
