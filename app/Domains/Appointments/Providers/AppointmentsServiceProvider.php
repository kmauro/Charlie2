<?php

namespace App\Domains\Appointments\Providers;

use App\Domains\Appointments\Models\Appointment;
use App\Domains\Appointments\Policies\AppointmentPolicy;
use App\Domains\Appointments\Repositories\Contracts\AppointmentRepository;
use App\Domains\Appointments\Repositories\Eloquent\EloquentAppointmentRepository;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppointmentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            AppointmentRepository::class,
            EloquentAppointmentRepository::class,
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        Gate::policy(Appointment::class, AppointmentPolicy::class);
    }
}
