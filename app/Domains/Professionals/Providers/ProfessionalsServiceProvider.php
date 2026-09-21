<?php

namespace App\Domains\Professionals\Providers;

use App\Domains\Professionals\Models\Professional;
use App\Domains\Professionals\Policies\ProfessionalPolicy;
use App\Domains\Professionals\Repositories\Contracts\ProfessionalRepository;
use App\Domains\Professionals\Repositories\Eloquent\EloquentProfessionalRepository;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class ProfessionalsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ProfessionalRepository::class, EloquentProfessionalRepository::class);
    }

    public function boot(): void
    {
        Gate::policy(Professional::class, ProfessionalPolicy::class);
    }
}
