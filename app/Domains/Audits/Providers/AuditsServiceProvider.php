<?php

namespace App\Domains\Audits\Providers;

use App\Domains\Audits\Commands\PruneAuditLogs;
use App\Domains\Audits\Repositories\Contracts\AuditLogRepository;
use App\Domains\Audits\Repositories\Eloquent\EloquentAuditLogRepository;
use Illuminate\Support\ServiceProvider;

class AuditsServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public $bindings = [
        AuditLogRepository::class => EloquentAuditLogRepository::class,
    ];

    public function boot(): void
    {
        $this->commands([PruneAuditLogs::class]);
    }
}
