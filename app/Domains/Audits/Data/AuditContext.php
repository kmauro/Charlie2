<?php

namespace App\Domains\Audits\Data;

final readonly class AuditContext
{
    public function __construct(
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
        public string $outcome = 'success',
        public ?string $reason = null,
        public ?string $correlationId = null,
    ) {}
}
