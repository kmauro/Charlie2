<?php

namespace App\Domains\Organizations\Providers;

use App\Domains\Organizations\Models\LegalEntity;
use App\Domains\Organizations\Models\Location;
use App\Domains\Organizations\Policies\LegalEntityPolicy;
use App\Domains\Organizations\Policies\LocationPolicy;
use App\Domains\Organizations\Repositories\Contracts\OrganizationRepository;
use App\Domains\Organizations\Repositories\Eloquent\EloquentOrganizationRepository;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class OrganizationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            OrganizationRepository::class,
            EloquentOrganizationRepository::class,
        );
    }

    public function boot(): void
    {
        Gate::policy(LegalEntity::class, LegalEntityPolicy::class);
        Gate::policy(Location::class, LocationPolicy::class);
    }
}
