<?php

use App\Domains\Audits\Data\AuditContext;
use App\Domains\Audits\Models\AuditLog;
use App\Domains\Audits\Services\AuditService;
use App\Domains\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('records an audit entry with explicit actor resource and context', function () {
    $actor = User::factory()->create();
    $resource = User::factory()->create([
        'name' => 'Recurso administrativo',
    ]);
    $beforeValues = [
        'name' => 'Nombre anterior',
        'status' => 'active',
    ];
    $afterValues = [
        'name' => 'Nombre actualizado',
        'status' => 'suspended',
    ];

    $auditLog = app(AuditService::class)->record(
        event: 'user.updated',
        auditable: $resource,
        beforeValues: $beforeValues,
        afterValues: $afterValues,
        actor: $actor,
        context: new AuditContext(
            ipAddress: '2001:db8::1',
            userAgent: 'Audit test agent',
            outcome: 'denied',
            reason: 'Permiso insuficiente',
            correlationId: 'request-01JTESTCORRELATION',
        ),
    );

    expect($auditLog->actor_id)->toBe($actor->getKey())
        ->and($auditLog->event)->toBe('user.updated')
        ->and($auditLog->auditable_type)->toBe($resource->getMorphClass())
        ->and($auditLog->auditable_id)->toBe((string) $resource->getKey())
        ->and($auditLog->before_values)->toBe($beforeValues)
        ->and($auditLog->after_values)->toBe($afterValues)
        ->and($auditLog->outcome)->toBe('denied')
        ->and($auditLog->reason)->toBe('Permiso insuficiente')
        ->and($auditLog->correlation_id)->toBe('request-01JTESTCORRELATION')
        ->and($auditLog->ip_address)->toBe('2001:db8::1')
        ->and($auditLog->user_agent)->toBe('Audit test agent')
        ->and($auditLog->created_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($auditLog->getAttributes())->not->toHaveKey('updated_at')
        ->and($auditLog->actor->is($actor))->toBeTrue()
        ->and($auditLog->auditable->is($resource))->toBeTrue()
        ->and($auditLog->toArray())->not->toHaveKeys([
            'before_values',
            'after_values',
            'reason',
        ]);

    $storedReason = DB::table('audit_logs')
        ->where('id', $auditLog->getKey())
        ->value('reason');

    expect($storedReason)->toBeString()
        ->and($storedReason)->not->toBe('Permiso insuficiente')
        ->and($storedReason)->not->toContain('Permiso insuficiente');
});

it('encrypts administrative snapshots in the database', function () {
    $resource = User::factory()->create([
        'name' => 'El recurso no debe copiarse automáticamente',
    ]);
    $beforeValues = [
        'name' => 'Nombre confidencial anterior',
    ];
    $afterValues = [
        'name' => 'Nombre confidencial posterior',
    ];

    $auditLog = app(AuditService::class)->record(
        event: 'user.updated',
        auditable: $resource,
        beforeValues: $beforeValues,
        afterValues: $afterValues,
    );
    $storedValues = DB::table('audit_logs')
        ->where('id', $auditLog->getKey())
        ->first(['before_values', 'after_values']);

    expect($storedValues)->not->toBeNull()
        ->and($storedValues->before_values)->toBeString()
        ->and($storedValues->after_values)->toBeString()
        ->and($storedValues->before_values)->not->toBe(json_encode($beforeValues))
        ->and($storedValues->after_values)->not->toBe(json_encode($afterValues))
        ->and($storedValues->before_values)->not->toContain('Nombre confidencial anterior')
        ->and($storedValues->after_values)->not->toContain('Nombre confidencial posterior')
        ->and($storedValues->after_values)->not->toContain(
            'El recurso no debe copiarse automáticamente',
        )
        ->and($auditLog->before_values)->toBe($beforeValues)
        ->and($auditLog->after_values)->toBe($afterValues);
});

it('uses safe defaults without reading actor or request context implicitly', function () {
    $auditLog = app(AuditService::class)->recordResource(
        event: 'user.searched',
        auditableType: User::class,
    );

    expect($auditLog->actor_id)->toBeNull()
        ->and($auditLog->auditable_type)->toBe(User::class)
        ->and($auditLog->auditable_id)->toBeNull()
        ->and($auditLog->auditable)->toBeNull()
        ->and($auditLog->outcome)->toBe('success')
        ->and($auditLog->reason)->toBeNull()
        ->and($auditLog->ip_address)->toBeNull()
        ->and($auditLog->user_agent)->toBeNull()
        ->and(Str::isUuid($auditLog->correlation_id))->toBeTrue()
        ->and($auditLog->before_values)->toBe([])
        ->and($auditLog->after_values)->toBe([]);
});

it('is immutable even when model events are muted', function () {
    $resource = User::factory()->create();
    $auditLog = app(AuditService::class)->record(
        event: 'user.created',
        auditable: $resource,
        afterValues: ['status' => 'active'],
    );

    expect(
        fn () => $auditLog->update(['event' => 'user.changed']),
    )->toThrow(LogicException::class);
    expect(
        fn () => $auditLog->updateQuietly(['event' => 'user.changed']),
    )->toThrow(LogicException::class);
    expect(
        fn () => $auditLog->delete(),
    )->toThrow(LogicException::class);
    expect(
        fn () => $auditLog->deleteQuietly(),
    )->toThrow(LogicException::class);

    $this->assertModelExists($auditLog);
    expect($auditLog->refresh()->event)->toBe('user.created');
});

it('participates in the transaction that invokes it', function () {
    $resource = User::factory()->create();

    try {
        DB::transaction(function () use ($resource): void {
            app(AuditService::class)->record(
                event: 'user.transaction_rolled_back',
                auditable: $resource,
            );

            throw new RuntimeException('Force rollback');
        });
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Force rollback');
    }

    expect(
        AuditLog::query()
            ->where('event', 'user.transaction_rolled_back')
            ->exists(),
    )->toBeFalse();
});

it('keeps the audit entry when its actor is deleted', function () {
    $actor = User::factory()->create();
    $resource = User::factory()->create();
    $auditLog = app(AuditService::class)->record(
        event: 'user.viewed',
        auditable: $resource,
        actor: $actor,
    );

    $actor->delete();

    expect($auditLog->refresh()->actor_id)->toBeNull();
    $this->assertModelExists($auditLog);
});
