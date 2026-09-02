<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StoredFileController;
use App\Http\Controllers\SchoolAssetController;

/*
|--------------------------------------------------------------------------
| Public / Auth Routes
|--------------------------------------------------------------------------
*/
Route::get('/', fn() => redirect()->route('login'));
Route::get('/maintenance/login', [AuthenticatedSessionController::class, 'maintenanceLogin'])->name('maintenance.login');
Route::get('/school-logo', [SchoolAssetController::class, 'logo'])->name('school.logo');
Route::middleware('guest')->group(function () {
    Route::get('/login',  [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);

    Route::get('/forgot-password',  [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::get('/files/notes/{note}', [StoredFileController::class, 'note'])
    ->middleware(['auth', 'verified'])->name('files.notes');

/*
|--------------------------------------------------------------------------
| Authenticated Routes — grouped by role
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified'])->group(function () {

    // Admin / Principal / Deputy / HOD
    Route::middleware(['role:admin|super-admin|headteacher|principal|deputy-principal|deputy|hod'])
        ->prefix('admin')
        ->name('admin.')
        ->group(base_path('routes/admin.php'));

    // Teacher / Class Teacher / HOD
    Route::middleware(['role:admin|super-admin|teacher|class-teacher|pre-primary-teacher|lower-primary-teacher|upper-primary-teacher|junior-secondary-teacher|hod|headteacher|principal|deputy-principal|deputy'])
        ->prefix('teacher')
        ->name('teacher.')
        ->group(base_path('routes/teacher.php'));

    // Parent / Guardian
    Route::middleware(['role:admin|super-admin|parent'])
        ->prefix('parent')
        ->name('parent.')
        ->group(base_path('routes/parent.php'));

    // Finance / Bursar
    Route::middleware(['role:admin|super-admin|bursar|headteacher|principal'])
        ->prefix('finance')
        ->name('finance.')
        ->group(base_path('routes/finance.php'));
});
