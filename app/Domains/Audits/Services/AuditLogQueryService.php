<?php

namespace App\Domains\Audits\Services;

use App\Domains\Audits\Models\AuditLog;
use App\Domains\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AuditLogQueryService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, AuditLog>
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->query($filters)
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /** @return Collection<int, User> */
    public function actors(): Collection
    {
        return User::query()
            ->whereHas('auditLogs')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /** @param array<string, mixed> $filters */
    public function exportQuery(array $filters): Builder
    {
        return $this->query($filters)->oldest('id');
    }

    /** @param array<string, mixed> $filters */
    private function query(array $filters): Builder
    {
        return AuditLog::query()
            ->with('actor:id,name')
            ->when(
                $filters['from'] ?? null,
                fn (Builder $query, string $from): Builder => $query
                    ->where('created_at', '>=', $from.' 00:00:00'),
            )
            ->when(
                $filters['to'] ?? null,
                fn (Builder $query, string $to): Builder => $query
                    ->where('created_at', '<=', $to.' 23:59:59'),
            )
            ->when(
                $filters['actor_id'] ?? null,
                fn (Builder $query, int|string $actorId): Builder => $query
                    ->where('actor_id', (int) $actorId),
            )
            ->when(
                $filters['event'] ?? null,
                fn (Builder $query, string $event): Builder => $query
                    ->where('event', 'like', '%'.Str::squish($event).'%'),
            )
            ->when(
                $filters['outcome'] ?? null,
                fn (Builder $query, string $outcome): Builder => $query
                    ->where('outcome', $outcome),
            )
            ->when(
                $filters['resource'] ?? null,
                fn (Builder $query, string $resource): Builder => $query
                    ->where('auditable_type', 'like', '%'.Str::squish($resource).'%'),
            );
    }
}
