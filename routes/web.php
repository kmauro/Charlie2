<?php

use App\Domains\Appointments\Controllers\AppointmentController;
use App\Domains\Audits\Controllers\AuditLogController;
use App\Domains\Auth\Controllers\AuthenticatedSessionController;
use App\Domains\Auth\Controllers\RegisteredUserController;
use App\Domains\Auth\Middleware\EnsureUserIsActive;
use App\Domains\Dashboard\Controllers\DashboardController;
use App\Domains\ExternalClinicalRecords\Controllers\ExternalClinicalRecordController;
use App\Domains\Organizations\Controllers\LegalEntityController;
use App\Domains\Organizations\Controllers\OrganizationController;
use App\Domains\Patients\Controllers\PatientController;
use App\Domains\Professionals\Controllers\ProfessionalController;
use App\Domains\Users\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');

    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::get('/register/{user}', [RegisteredUserController::class, 'accept'])
        ->middleware('signed')
        ->name('register.accept');
    Route::post('/register/{user}', [RegisteredUserController::class, 'store'])
        ->middleware('signed')
        ->name('register.store');
});

Route::middleware(['auth', EnsureUserIsActive::class])->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::controller(AuditLogController::class)->prefix('auditoria')->name('audits.')->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/exportar', 'export')->name('export');
    });

    Route::controller(UserController::class)->prefix('usuarios')->name('users.')->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/nuevo', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{user}/editar', 'edit')->name('edit');
        Route::put('/{user}', 'update')->name('update');
        Route::delete('/{user}', 'destroy')->name('destroy');
        Route::patch('/{user}/restaurar', 'restore')->name('restore');
        Route::post('/{user}/reinvitacion', 'reinvite')->name('reinvite');
    });

    Route::controller(ProfessionalController::class)->prefix('profesionales')->name('professionals.')->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/nuevo', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{professional}/editar', 'edit')->name('edit');
        Route::put('/{professional}', 'update')->name('update');
        Route::delete('/{professional}', 'destroy')->name('destroy');
        Route::patch('/{professional}/restaurar', 'restore')->name('restore');
    });

    Route::controller(PatientController::class)->prefix('pacientes')->name('patients.')->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/nuevo', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get(
            '/{patient}/historia-clinica-externa',
            ExternalClinicalRecordController::class,
        )->name('external_clinical_record');
        Route::get('/{patient}/editar', 'edit')->name('edit');
        Route::get('/{patient}', 'show')->name('show');
        Route::put('/{patient}', 'update')->name('update');
        Route::delete('/{patient}', 'destroy')->name('destroy');
        Route::patch('/{patient}/restaurar', 'restore')->name('restore');
    });

    Route::controller(OrganizationController::class)->prefix('organizacion/sedes')->name('organizations.')->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/nueva', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{location}/editar', 'edit')->name('edit');
        Route::put('/{location}', 'update')->name('update');
        Route::delete('/{location}', 'destroy')->name('destroy');
        Route::patch('/{location}/restaurar', 'restore')->name('restore');
    });

    Route::controller(LegalEntityController::class)->prefix('organizacion/razones-sociales')->name('legal_entities.')->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/nueva', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{legalEntity}/editar', 'edit')->name('edit');
        Route::put('/{legalEntity}', 'update')->name('update');
        Route::delete('/{legalEntity}', 'destroy')->name('destroy');
        Route::patch('/{legalEntity}/restaurar', 'restore')->name('restore');
    });

    Route::controller(AppointmentController::class)->prefix('turnos')->name('appointments.')->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/crear', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{appointment}/editar', 'edit')->name('edit');
        Route::put('/{appointment}', 'update')->name('update');
        Route::patch('/{appointment}/estado', 'updateStatus')->name('status');
        Route::patch('/{appointment}/cancelar', 'cancel')->name('cancel');
    });
});
