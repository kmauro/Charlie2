<?php

use App\Http\Controllers\Controller;

arch('domain controllers stay inside their domain layer')
    ->expect([
        'App\Domains\Auth\Controllers',
        'App\Domains\Dashboard\Controllers',
        'App\Domains\ExternalClinicalRecords\Controllers',
        'App\Domains\Organizations\Controllers',
        'App\Domains\Patients\Controllers',
        'App\Domains\Professionals\Controllers',
        'App\Domains\Users\Controllers',
    ])
    ->toExtend(Controller::class)
    ->toHaveSuffix('Controller');

arch('the application does not depend on the legacy user model namespace')
    ->expect('App')
    ->not->toUse('App\Models\User');

arch('user domain factories stay inside their database layer')
    ->expect('App\Domains\Users\Database\Factories')
    ->toHaveSuffix('Factory')
    ->toExtend(Illuminate\Database\Eloquent\Factories\Factory::class);

arch('user domain seeders stay inside their database layer')
    ->expect('App\Domains\Users\Database\Seeders')
    ->toHaveSuffix('Seeder')
    ->toExtend(Illuminate\Database\Seeder::class);
