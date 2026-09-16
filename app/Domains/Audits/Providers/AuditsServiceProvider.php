<?php

namespace App\Domains\Audits\Providers;

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
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
    }
}
