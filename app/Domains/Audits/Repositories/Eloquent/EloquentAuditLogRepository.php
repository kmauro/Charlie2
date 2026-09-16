<?php

namespace App\Domains\Audits\Repositories\Eloquent;

use App\Domains\Audits\Models\AuditLog;
use App\Domains\Audits\Repositories\Contracts\AuditLogRepository;
use DateTimeInterface;

class EloquentAuditLogRepository implements AuditLogRepository
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
    public function create(array $attributes): AuditLog
    {
        return AuditLog::query()->create($attributes);
    }

    public function countCreatedBefore(DateTimeInterface $cutoff): int
    {
        return AuditLog::query()->where('created_at', '<', $cutoff)->count();
    }

    public function deleteCreatedBefore(DateTimeInterface $cutoff): int
    {
        return AuditLog::query()->where('created_at', '<', $cutoff)->toBase()->delete();
    }
}
