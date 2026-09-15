<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\ManagerController;
use App\Http\Controllers\ManagerVacationController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\UserDirectoryController;
use App\Http\Controllers\VacationController;
use App\Http\Controllers\VacationEntitlementController;
use Illuminate\Support\Facades\Route;

Route::prefix(config('app.route_prefix'))->group(function () {
    Route::get('/health/ready', [HealthController::class, 'ready'])->name('health.ready');
    Route::get('/', function () {
        if (! auth()->check()) {
            return redirect()->route('login');
        }

        return redirect()->route(auth()->user()->isManager()
            ? 'manager.dashboard'
            : 'employee.dashboard');
    });
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AuthController::class, 'show'])->name('login');
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/cadastro', [AuthController::class, 'register'])->name('register');
    });
    Route::middleware('guest')->group(function () {
        Route::get('/esqueci-a-senha', [PasswordController::class, 'request'])->name('password.request');
        Route::post('/esqueci-a-senha', [PasswordController::class, 'email'])->middleware('throttle:6,1')->name('password.email');
        Route::get('/redefinir-senha/{token}', [PasswordController::class, 'reset'])->name('password.reset');
        Route::post('/redefinir-senha', [PasswordController::class, 'update'])->name('password.update');
    });
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');
    Route::middleware(['auth', 'role:employee,manager,super_admin'])->group(function () {
        Route::get('/painel', [EmployeeController::class, 'index'])->name('employee.dashboard');
        Route::post('/solicitacoes', [EmployeeController::class, 'store'])->name('employee.requests.store');
        Route::get('/ferias', [VacationController::class, 'index'])->name('vacations.index');
        Route::post('/ferias', [VacationController::class, 'store'])->name('vacations.store');
    });
    Route::middleware(['auth', 'role:manager,super_admin'])->prefix('gestor')->group(function () {
        Route::get('/', [ManagerController::class, 'index'])->name('manager.dashboard');
        Route::post('/solicitacoes/{workRequest}/analisar', [ManagerController::class, 'review'])->name('manager.review');
        Route::post('/solicitacoes/analisar-em-lote', [ManagerController::class, 'reviewBatch'])->name('manager.review.batch');
        Route::get('/exportar', [ManagerController::class, 'export'])->name('manager.export');
        Route::get('/ferias', [ManagerVacationController::class, 'index'])->name('manager.vacations.index');
        Route::post('/ferias/{vacation}/analisar', [ManagerVacationController::class, 'review'])->name('manager.vacations.review');
        Route::get('/ferias/exportar', [ManagerVacationController::class, 'export'])->name('manager.vacations.export');
    });
    Route::middleware(['auth', 'role:super_admin'])->prefix('admin')->group(function () {
        Route::post('/equipes', [AdminController::class, 'team'])->name('admin.teams.store');
        Route::patch('/equipes/{team}', [AdminController::class, 'toggleTeam'])->name('admin.teams.toggle');
        Route::post('/calendario', [AdminController::class, 'holiday'])->name('admin.holidays.store');
        Route::delete('/calendario/{holiday}', [AdminController::class, 'deleteHoliday'])->name('admin.holidays.destroy');
        Route::post('/delegacoes', [AdminController::class, 'delegation'])->name('admin.delegations.store');
        Route::delete('/delegacoes/{delegation}', [AdminController::class, 'deleteDelegation'])->name('admin.delegations.destroy');
        Route::get('/auditoria', [AdminController::class, 'audits'])->name('admin.audits');
        Route::get('/operacao', [AdminController::class, 'operations'])->name('admin.operations');
        Route::get('/usuarios', [UserDirectoryController::class, 'index'])->name('admin.users.index');
        Route::post('/usuarios', [UserDirectoryController::class, 'store'])->name('admin.users.store');
        Route::put('/usuarios/{user}', [UserDirectoryController::class, 'update'])->name('admin.users.update');
        Route::patch('/usuarios/{user}/status', [UserDirectoryController::class, 'toggle'])->name('admin.users.toggle');
        Route::post('/usuarios/{user}/acesso', [UserDirectoryController::class, 'passwordLink'])->middleware('throttle:6,1')->name('admin.users.password-link');
        Route::post('/ferias/saldos', [VacationEntitlementController::class, 'store'])->name('admin.vacation-entitlements.store');
        Route::put('/ferias/saldos/{entitlement}', [VacationEntitlementController::class, 'update'])->name('admin.vacation-entitlements.update');
        Route::put('/ferias/{vacation}', [ManagerVacationController::class, 'correct'])->name('admin.vacations.correct');
        Route::post('/ferias/{vacation}/cancelar', [ManagerVacationController::class, 'cancel'])->name('admin.vacations.cancel');
    });
});
