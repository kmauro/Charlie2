<?php

use App\Domains\Audits\Mail\AuditRetentionWarningMail;
use App\Domains\Audits\Models\AuditLog;
use App\Domains\Users\Enums\RoleCode;
use App\Domains\Users\Enums\UserPermission;
use App\Domains\Users\Models\Role;
use App\Domains\Users\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function auditorUser(bool $canExport = true): User
{
    return User::factory()->create([
        'role_id' => Role::query()->where('code', RoleCode::Auditor->value)->value('id'),
        'permissions' => $canExport ? [UserPermission::ExportAudit->value] : [],
    ]);
}

function oldAuditLog(): AuditLog
{
    return AuditLog::query()->forceCreate([
        'event' => 'old.event',
        'auditable_type' => User::class,
        'auditable_id' => null,
        'before_values' => [],
        'after_values' => [],
        'outcome' => 'success',
        'correlation_id' => fake()->uuid(),
        'created_at' => now()->subYear()->subDay(),
    ]);
}

it('allows only auditors to consult the audit interface', function () {
    $auditor = auditorUser();
    oldAuditLog();

    $this->actingAs($auditor)
        ->get(route('audits.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('audits/views/Index')
            ->has('logs.data', 1)
            ->where('canExport', true));

    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('audits.index'))
        ->assertForbidden();
});

it('exports only when the auditor has the individual permission', function () {
    oldAuditLog();

    $this->actingAs(auditorUser(false))
        ->get(route('audits.export'))
        ->assertForbidden();

    $this->actingAs(auditorUser())
        ->get(route('audits.export'))
        ->assertSuccessful()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
});

it('notifies every active authorized auditor before pruning expired logs', function () {
    Mail::fake();
    $auditor = auditorUser();
    $expiredAudit = oldAuditLog();

    $this->artisan('audits:prune')->assertSuccessful();

    Mail::assertSent(
        AuditRetentionWarningMail::class,
        fn (AuditRetentionWarningMail $mail): bool => $mail->hasTo($auditor->email)
            && $mail->recordsCount === 1,
    );
    expect(AuditLog::query()->whereKey($expiredAudit)->exists())->toBeFalse()
        ->and(AuditLog::query()->where('event', 'audit.retention_pruned')->exists())->toBeTrue();
});

it('cancels retention when no active auditor can be notified', function () {
    $expiredAudit = oldAuditLog();

    $this->artisan('audits:prune')->assertFailed();

    expect(AuditLog::query()->whereKey($expiredAudit)->exists())->toBeTrue();
});
