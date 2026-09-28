<?php

namespace App\Providers;

use App\Domains\Users\Enums\RoleCode;
use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class BillingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Gate::define('billing-operate', fn (User $user): bool => in_array($user->role?->code, [RoleCode::SuperAdmin, RoleCode::Administrator, RoleCode::Manager, RoleCode::Accounting, RoleCode::Reception, RoleCode::Sales], true));
    }
}
