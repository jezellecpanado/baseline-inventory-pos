<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'create'])->name('login');
Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:6,1')->name('login.store');
Route::middleware(['login.auth', 'active'])->group(function (): void {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/admin', [DashboardController::class, 'index'])->middleware('role:admin')->name('admin.index');
    Route::get('/warehouse', [WorkspaceController::class, 'warehouse'])->middleware('role:admin,warehouse_staff')->name('warehouse');
    Route::post('/warehouse/{operation}', [WorkspaceController::class, 'storeWarehouse'])->middleware('role:admin,warehouse_staff')->name('warehouse.store');
    Route::get('/pos', [WorkspaceController::class, 'pos'])->middleware('role:admin,pos_staff')->name('pos');
    Route::post('/pos/sale', [WorkspaceController::class, 'storePosSale'])->middleware('role:admin,pos_staff')->name('pos.sale');
    Route::post('/pos/withdrawal', [WorkspaceController::class, 'storePosWithdrawal'])->middleware('role:admin')->name('pos.withdrawal');
    Route::post('/pos/exchange', [WorkspaceController::class, 'storeExchange'])->middleware('role:admin,pos_staff')->name('pos.exchange');
    Route::post('/admin/{action}', [AdminController::class, 'store'])->middleware('role:admin')->name('admin.store');
    Route::post('/admin/void/{transaction}', [AdminController::class, 'void'])->middleware('role:admin')->name('admin.void');
});
