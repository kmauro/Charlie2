<?php

namespace App\Domains\Operations\Providers;

use App\Domains\Operations\Models\Admission;
use App\Domains\Operations\Models\EmergencyVisit;
use App\Domains\Operations\Policies\AdmissionPolicy;
use App\Domains\Operations\Policies\EmergencyVisitPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class OperationsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(EmergencyVisit::class, EmergencyVisitPolicy::class);
        Gate::policy(Admission::class, AdmissionPolicy::class);
    }
}
