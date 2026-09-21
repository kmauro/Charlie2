<?php

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Seeder;

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

arch('factories use the default Laravel database namespace')
    ->expect('Database\Factories')
    ->toHaveSuffix('Factory')
    ->toExtend(Factory::class);

arch('seeders use the default Laravel database namespace')
    ->expect('Database\Seeders')
    ->toHaveSuffix('Seeder')
    ->toExtend(Seeder::class);

it('keeps database artifacts outside the domain directories', function () {
    $projectRoot = dirname(__DIR__, 2);
    $domainFiles = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $projectRoot.'/app/Domains',
            FilesystemIterator::SKIP_DOTS,
        ),
    );
    $domainDatabaseFiles = collect(iterator_to_array($domainFiles, false))
        ->filter(fn (SplFileInfo $file): bool => str_contains(
            str_replace('\\', '/', $file->getPathname()),
            '/Database/',
        ));

    expect($domainDatabaseFiles)->toBeEmpty()
        ->and(glob($projectRoot.'/database/factories/*.php'))->not->toBeEmpty()
        ->and(glob($projectRoot.'/database/migrations/*.php'))->not->toBeEmpty()
        ->and(glob($projectRoot.'/database/seeders/*.php'))->not->toBeEmpty();
});
