<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rute BLJ — dikelompokkan per peran. Setiap tahap menambah rute di file ini.
|--------------------------------------------------------------------------
*/

// ---- Tamu (belum login) ----
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:30,1');
    Route::get('/lupa-password', [ForgotPasswordController::class, 'request'])->name('password.request');
    Route::post('/lupa-password', [ForgotPasswordController::class, 'send'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [ForgotPasswordController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [ForgotPasswordController::class, 'update'])->name('password.update');
});

Route::middleware(['auth', 'account'])->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/ganti-password', [PasswordController::class, 'edit'])->name('password.change');
    Route::post('/ganti-password', [PasswordController::class, 'update'])->name('password.change.update');

    Route::get('/', HomeController::class)->name('home');
    Route::get('/profil', ProfileController::class)->name('profile');

    // ---- Karyawan ----
    Route::middleware('role:employee')->group(function () {
        Route::view('/beranda', 'employee.home-placeholder')->name('employee.home');
    });

    // ---- Admin L&D ----
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/akun', [Admin\UserController::class, 'index'])->name('users.index');
        Route::post('/akun/{user}/reset-password', [Admin\UserController::class, 'resetPassword'])->name('users.reset');
        Route::post('/akun/{user}/buka-kunci', [Admin\UserController::class, 'unlock'])->name('users.unlock');
        Route::post('/akun/{user}/peran', [Admin\UserController::class, 'updateRoles'])->name('users.roles');
        Route::get('/audit-log', [Admin\AuditLogController::class, 'index'])->name('audit.index');

        // Tahap 3 — Karyawan & master data
        Route::get('/karyawan', [Admin\EmployeeController::class, 'index'])->name('employees.index');
        Route::get('/karyawan/ekspor', [Admin\EmployeeController::class, 'export'])->name('employees.export');
        Route::get('/karyawan/impor', [Admin\EmployeeController::class, 'importForm'])->name('employees.import');
        Route::get('/karyawan/impor/template', [Admin\EmployeeController::class, 'template'])->name('employees.template');
        Route::post('/karyawan/impor/pratinjau', [Admin\EmployeeController::class, 'preview'])->name('employees.preview');
        Route::post('/karyawan/impor/simpan', [Admin\EmployeeController::class, 'commit'])->name('employees.commit');
        Route::get('/karyawan/impor/akun/{token}', [Admin\EmployeeController::class, 'credentials'])->name('employees.credentials');
        Route::get('/karyawan/{employee}', [Admin\EmployeeController::class, 'show'])->name('employees.show');
        Route::get('/karyawan/{employee}/edit', [Admin\EmployeeController::class, 'edit'])->name('employees.edit');
        Route::put('/karyawan/{employee}', [Admin\EmployeeController::class, 'update'])->name('employees.update');

        Route::get('/master/{type?}', [Admin\MasterDataController::class, 'index'])->name('master.index');
        Route::post('/master/{type}', [Admin\MasterDataController::class, 'store'])->name('master.store');
        Route::put('/master/{type}/{id}', [Admin\MasterDataController::class, 'update'])->name('master.update');
        Route::post('/master/{type}/{id}/toggle', [Admin\MasterDataController::class, 'toggle'])->name('master.toggle');
        Route::delete('/master/{type}/{id}', [Admin\MasterDataController::class, 'destroy'])->name('master.destroy');
    });
});
