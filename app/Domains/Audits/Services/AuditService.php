<?php

namespace App\Domains\Audits\Services;

use App\Domains\Audits\Data\AuditContext;
use App\Domains\Audits\Models\AuditLog;
use App\Domains\Audits\Repositories\Contracts\AuditLogRepository;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AuditService
{
    public function __construct(
        private AuditLogRepository $auditLogs,
    ) {}

    /**
     * The snapshots must contain only explicitly selected administrative values.
     *
     * @param  array<string, mixed>  $beforeValues
     * @param  array<string, mixed>  $afterValues
     */
    public function record(
        string $event,
        Model $auditable,
        array $beforeValues = [],
        array $afterValues = [],
        ?User $actor = null,
        ?AuditContext $context = null,
    ): AuditLog {
        $context ??= new AuditContext;
        $auditableId = $auditable->getKey();

        if ($auditableId === null) {
            throw new InvalidArgumentException(
                'El recurso auditado debe estar persistido.',
            );
        }

        return $this->recordResource(
            event: $event,
            auditableType: $auditable->getMorphClass(),
            auditableId: $auditableId,
            beforeValues: $beforeValues,
            afterValues: $afterValues,
            actor: $actor,
            context: $context,
        );
    }

    /**
     * The snapshots must contain only explicitly selected administrative values.
     *
     * @param  array<string, mixed>  $beforeValues
     * @param  array<string, mixed>  $afterValues
     */
    public function recordResource(
        string $event,
        string $auditableType,
        int|string|null $auditableId = null,
        array $beforeValues = [],
        array $afterValues = [],
        ?User $actor = null,
        ?AuditContext $context = null,
    ): AuditLog {
        $context ??= new AuditContext;

        return $this->auditLogs->create([
            'actor_id' => $actor === null ? null : (int) $actor->getKey(),
            'event' => $this->requiredValue($event, 'event', 160),
            'auditable_type' => $this->requiredValue(
                (string) Relation::getMorphAlias($auditableType),
                'auditable_type',
                191,
            ),
            'auditable_id' => $this->auditableId($auditableId),
            'before_values' => $beforeValues,
            'after_values' => $afterValues,
            'outcome' => $this->requiredValue(
                $context->outcome,
                'outcome',
                32,
            ),
            'reason' => $this->nullableValue($context->reason),
            'correlation_id' => $this->correlationId($context),
            'ip_address' => $this->ipAddress($context->ipAddress),
            'user_agent' => $this->nullableValue($context->userAgent),
        ]);
    }

    private function auditableId(int|string|null $auditableId): ?string
    {
        if ($auditableId === null) {
            return null;
        }

        return $this->requiredValue(
            (string) $auditableId,
            'auditable_id',
            191,
        );
    }

    private function correlationId(AuditContext $context): string
    {
        if (filled($context->correlationId)) {
            return $this->requiredValue(
                (string) $context->correlationId,
                'correlation_id',
                100,
            );
        }

        return (string) Str::uuid();
    }

    private function ipAddress(?string $ipAddress): ?string
    {
        $normalizedIpAddress = $this->nullableValue($ipAddress);

        if (
            $normalizedIpAddress !== null
            && filter_var($normalizedIpAddress, FILTER_VALIDATE_IP) === false
        ) {
            throw new InvalidArgumentException(
                'El contexto de auditoría contiene una dirección IP inválida.',
            );
        }

        return $normalizedIpAddress;
    }

    private function requiredValue(
        string $value,
        string $field,
        int $maximumLength,
    ): string {
        $normalizedValue = Str::squish($value);

        if ($normalizedValue === '') {
            throw new InvalidArgumentException(
                "El campo de auditoría [$field] es obligatorio.",
            );
        }

        if (Str::length($normalizedValue) > $maximumLength) {
            throw new InvalidArgumentException(
                "El campo de auditoría [$field] supera los $maximumLength caracteres.",
            );
        }

        return $normalizedValue;
    }

    private function nullableValue(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        return trim((string) $value);
    }
}
