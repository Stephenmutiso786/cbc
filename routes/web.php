<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public / Auth Routes
|--------------------------------------------------------------------------
*/
Route::get('/', fn() => redirect()->route('login'));
Route::get('/_diag/login', function () {
    try {
        return response(view('auth.login')->render(), 200)
            ->header('Content-Type', 'text/plain');
    } catch (\Throwable $e) {
        return response($e->getMessage()."\n\n".$e->getTraceAsString(), 500)
            ->header('Content-Type', 'text/plain');
    }
});
Route::get('/_diag/db', function () {
    try {
        \DB::connection()->getPdo();
        return response()->json(['ok' => true]);
    } catch (\Throwable $e) {
        return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
    }
});

Route::middleware('guest')->group(function () {
    Route::get('/login',  [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);

    Route::get('/forgot-password',  [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| Authenticated Routes — grouped by role
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified'])->group(function () {

    // Admin / Principal / Deputy / HOD
    Route::middleware(['role:super-admin|principal|deputy-principal|hod'])
        ->prefix('admin')
        ->name('admin.')
        ->group(base_path('routes/admin.php'));

    // Teacher / Class Teacher / HOD
    Route::middleware(['role:teacher|class-teacher|hod|principal|deputy-principal'])
        ->prefix('teacher')
        ->name('teacher.')
        ->group(base_path('routes/teacher.php'));

    // Parent / Guardian
    Route::middleware(['role:parent'])
        ->prefix('parent')
        ->name('parent.')
        ->group(base_path('routes/parent.php'));

    // Finance / Bursar
    Route::middleware(['role:bursar|super-admin|principal'])
        ->prefix('finance')
        ->name('finance.')
        ->group(base_path('routes/finance.php'));
});
