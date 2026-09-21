<?php

namespace App\Domains\Patients\Providers;

use App\Domains\Patients\Models\Patient;
use App\Domains\Patients\Policies\PatientPolicy;
use App\Domains\Patients\Repositories\Contracts\PatientRepository;
use App\Domains\Patients\Repositories\Eloquent\EloquentPatientRepository;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class PatientsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PatientRepository::class, EloquentPatientRepository::class);
    }

    public function boot(): void
    {
        Gate::policy(Patient::class, PatientPolicy::class);
    }
}
