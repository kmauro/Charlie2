<?php

namespace App\Domains\Audits\Repositories\Contracts;

use App\Domains\Audits\Models\AuditLog;
use DateTimeInterface;

interface AuditLogRepository
{
    /**
     * @param  array{
     *     actor_id: int|null,
     *     event: string,
     *     auditable_type: string,
     *     auditable_id: string|null,
     *     before_values: array<string, mixed>,
     *     after_values: array<string, mixed>,
     *     outcome: string,
     *     reason: string|null,
     *     correlation_id: string,
     *     ip_address: string|null,
     *     user_agent: string|null
     * }  $attributes
     */
    public function create(array $attributes): AuditLog;

    public function countCreatedBefore(DateTimeInterface $cutoff): int;

    public function deleteCreatedBefore(DateTimeInterface $cutoff): int;
}
