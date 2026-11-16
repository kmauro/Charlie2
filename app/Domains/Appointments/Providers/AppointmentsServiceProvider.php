<?php

namespace App\Domains\Appointments\Providers;

use App\Domains\Appointments\Models\Appointment;
use App\Domains\Appointments\Policies\AppointmentPolicy;
use App\Domains\Appointments\Repositories\Contracts\AppointmentRepository;
use App\Domains\Appointments\Repositories\Eloquent\EloquentAppointmentRepository;
use App\Domains\Appointments\Contracts\WhatsAppProvider;
use App\Domains\Appointments\Services\SimulatedWhatsAppProvider;
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
        $this->app->bind(WhatsAppProvider::class, SimulatedWhatsAppProvider::class);
    }

    public function boot(): void
    {
        Gate::policy(Appointment::class, AppointmentPolicy::class);
    }
}
