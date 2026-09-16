<?php

namespace App\Domains\Users;

use App\Domains\Users\Models\User;
use App\Domains\Users\Policies\UserPolicy;
use App\Domains\Users\Repositories\Contracts\RoleRepository;
use App\Domains\Users\Repositories\Contracts\UserRepository;
use App\Domains\Users\Repositories\Eloquent\EloquentRoleRepository;
use App\Domains\Users\Repositories\Eloquent\EloquentUserRepository;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class UsersServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public $bindings = [
        RoleRepository::class => EloquentRoleRepository::class,
        UserRepository::class => EloquentUserRepository::class,
    ];

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        Gate::policy(User::class, UserPolicy::class);
    }
}
