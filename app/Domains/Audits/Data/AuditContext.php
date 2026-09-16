<?php

namespace App\Domains\Audits\Data;

use Illuminate\Http\Request;

final readonly class AuditContext
{
    public function __construct(
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
        public string $outcome = 'success',
        public ?string $reason = null,
        public ?string $correlationId = null,
    ) {}

    public static function fromRequest(?Request $request = null): self
    {
        $request ??= request();
        $correlationId = $request->header('X-Request-ID');

        return new self(
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            correlationId: is_string($correlationId) ? $correlationId : null,
        );
    }
}
