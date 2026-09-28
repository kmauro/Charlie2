<?php

use App\Domains\Appointments\Providers\AppointmentsServiceProvider;
use App\Domains\Audits\Providers\AuditsServiceProvider;
use App\Domains\Organizations\Providers\OrganizationsServiceProvider;
use App\Domains\Patients\Providers\PatientsServiceProvider;
use App\Domains\Professionals\Providers\ProfessionalsServiceProvider;
use App\Domains\Users\Providers\UsersServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\BillingServiceProvider;

return [
    AppointmentsServiceProvider::class,
    AuditsServiceProvider::class,
    OrganizationsServiceProvider::class,
    PatientsServiceProvider::class,
    ProfessionalsServiceProvider::class,
    UsersServiceProvider::class,
    AppServiceProvider::class,
    BillingServiceProvider::class,
];
