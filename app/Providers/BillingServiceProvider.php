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
        Gate::define('billing-accounting', fn (User $user): bool => in_array($user->role?->code, [RoleCode::SuperAdmin, RoleCode::Administrator, RoleCode::Manager, RoleCode::Accounting], true));
        Gate::define('billing-invoice', fn (User $user): bool => in_array($user->role?->code, [RoleCode::SuperAdmin, RoleCode::Administrator, RoleCode::Manager, RoleCode::Sales], true));
        Gate::define('billing-payment', fn (User $user): bool => in_array($user->role?->code, [RoleCode::SuperAdmin, RoleCode::Administrator, RoleCode::Manager, RoleCode::Reception, RoleCode::Sales], true));
        Gate::define('billing-cash', fn (User $user): bool => in_array($user->role?->code, [RoleCode::SuperAdmin, RoleCode::Administrator, RoleCode::Manager, RoleCode::Sales], true));
        Gate::define('billing-full', fn (User $user): bool => in_array($user->role?->code, [RoleCode::SuperAdmin, RoleCode::Administrator, RoleCode::Manager], true));
    }
}
