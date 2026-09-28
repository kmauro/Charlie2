<?php

use App\Domains\Appointments\Controllers\AppointmentController;
use App\Domains\Appointments\Controllers\AppointmentWaitlistController;
use App\Domains\Audits\Controllers\AuditLogController;
use App\Domains\Auth\Controllers\AuthenticatedSessionController;
use App\Domains\Auth\Controllers\NewPasswordController;
use App\Domains\Auth\Controllers\PasswordResetLinkController;
use App\Domains\Auth\Controllers\RegisteredUserController;
use App\Domains\Auth\Middleware\EnsureUserIsActive;
use App\Domains\Dashboard\Controllers\DashboardController;
use App\Domains\ExternalClinicalRecords\Controllers\ExternalClinicalRecordController;
use App\Domains\Organizations\Controllers\LegalEntityController;
use App\Domains\Organizations\Controllers\OrganizationController;
use App\Domains\Patients\Controllers\PatientController;
use App\Domains\Professionals\Controllers\OperationalAgendaController;
use App\Domains\Professionals\Controllers\ProfessionalController;
use App\Domains\Users\Controllers\UserController;
use App\Http\Controllers\Domains\Billing\Controllers\BillingController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    Route::get('/recuperar-contrasena', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');
    Route::post('/recuperar-contrasena', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('password.email');
    Route::get('/restablecer-contrasena/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');
    Route::post('/restablecer-contrasena', [NewPasswordController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('password.update');

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

    Route::controller(BillingController::class)->prefix('facturacion')->name('billing.')->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/facturas', 'storeInvoice')->name('invoices.store');
        Route::post('/facturas/{invoice}/notas-de-credito', 'storeCreditNote')->name('credit-notes.store');
        Route::post('/cobros', 'storePayment')->name('payments.store');
        Route::post('/facturas/{invoice}/devoluciones', 'storeRefund')->name('refunds.store');
        Route::post('/cajas/{location}/apertura', 'openCashRegister')->name('cash-registers.open');
        Route::post('/cajas/{location}/cierre', 'closeCashRegister')->name('cash-registers.close');
        Route::post('/devoluciones/{refund}/autorizar', 'authorizeRefund')->name('refunds.authorize');
        Route::post('/devoluciones/{refund}/registrar', 'registerRefund')->name('refunds.register');
    });

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

    Route::controller(OperationalAgendaController::class)->prefix('agenda-operativa')->name('operational_agenda.')->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/consultorios', 'storeRoom')->name('rooms.store');
        Route::delete('/consultorios/{room}', 'destroyRoom')->name('rooms.destroy');
        Route::post('/feriados', 'storeHoliday')->name('holidays.store');
        Route::delete('/feriados/{holiday}', 'destroyHoliday')->name('holidays.destroy');
        Route::post('/licencias', 'storeLeave')->name('leaves.store');
        Route::delete('/licencias/{leave}', 'destroyLeave')->name('leaves.destroy');
        Route::post('/excepciones', 'storeException')->name('exceptions.store');
        Route::delete('/excepciones/{exception}', 'destroyException')->name('exceptions.destroy');
        Route::post('/franjas/{schedule}/descansos', 'storeBreak')->name('breaks.store');
        Route::delete('/descansos/{break}', 'destroyBreak')->name('breaks.destroy');
        Route::patch('/franjas/{schedule}/consultorio', 'updateScheduleRoom')->name('schedules.room');
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
        Route::post('/recurrentes', 'storeRecurring')->name('recurring.store');
        Route::get('/{appointment}/editar', 'edit')->name('edit');
        Route::put('/{appointment}', 'update')->name('update');
        Route::patch('/{appointment}/estado', 'updateStatus')->name('status');
        Route::patch('/{appointment}/cancelar', 'cancel')->name('cancel');
    });

    Route::controller(AppointmentWaitlistController::class)->prefix('lista-espera')->name('appointments.waitlist.')->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('/{waitlistEntry}/asignar', 'createSchedule')->name('schedule.create');
        Route::post('/{waitlistEntry}/asignar', 'schedule')->name('schedule.store');
    });
});
