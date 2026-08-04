<?php
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ManagerController;
use Illuminate\Support\Facades\Route;
Route::prefix(config('app.route_prefix'))->group(function () {
Route::get('/', fn () => redirect()->route('login'));
Route::middleware('guest')->group(function(){ Route::get('/login',[AuthController::class,'show'])->name('login'); Route::post('/login',[AuthController::class,'login']); Route::post('/cadastro',[AuthController::class,'register'])->name('register'); });
Route::post('/logout',[AuthController::class,'logout'])->middleware('auth')->name('logout');
Route::middleware(['auth','role:employee,manager,super_admin'])->group(function(){ Route::get('/painel',[EmployeeController::class,'index'])->name('employee.dashboard'); Route::post('/solicitacoes',[EmployeeController::class,'store'])->name('employee.requests.store'); });
Route::middleware(['auth','role:manager,super_admin'])->prefix('gestor')->group(function(){ Route::get('/',[ManagerController::class,'index'])->name('manager.dashboard'); Route::post('/solicitacoes/{workRequest}/analisar',[ManagerController::class,'review'])->name('manager.review'); Route::get('/exportar',[ManagerController::class,'export'])->name('manager.export'); });
Route::middleware(['auth','role:super_admin'])->prefix('admin')->group(function(){ Route::post('/equipes',[AdminController::class,'team'])->name('admin.teams.store'); Route::patch('/equipes/{team}',[AdminController::class,'toggleTeam'])->name('admin.teams.toggle'); Route::post('/usuarios',[AdminController::class,'user'])->name('admin.users.store'); Route::put('/usuarios/{user}',[AdminController::class,'user'])->name('admin.users.update'); Route::patch('/usuarios/{user}/status',[AdminController::class,'toggleUser'])->name('admin.users.toggle'); });
});
