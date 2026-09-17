<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StoredFileController;
use App\Http\Controllers\SchoolAssetController;
use App\Http\Controllers\LegalConsentController;
use App\Http\Controllers\CookieConsentController;
use App\Http\Controllers\AccountPasswordController;

/*
|--------------------------------------------------------------------------
| Public / Auth Routes
|--------------------------------------------------------------------------
*/
Route::get('/', fn() => redirect()->route('login'));
Route::get('/learning/miyagi', fn() => redirect()->away(config('services.miyagi_labs.url')))->name('learning.miyagi');
Route::get('/maintenance/login', [AuthenticatedSessionController::class, 'maintenanceLogin'])->name('maintenance.login');
Route::get('/school-logo', [SchoolAssetController::class, 'logo'])->name('school.logo');
Route::view('/verify', 'verify')->name('verify');
Route::view('/terms', 'legal.terms')->name('legal.terms');
Route::view('/privacy', 'legal.privacy')->name('legal.privacy');
Route::view('/school-locked', 'school-locked')->middleware('auth')->name('school.locked');
Route::view('/legal/acceptance', 'legal.acceptance')->middleware('auth')->name('legal.acceptance');
Route::post('/legal/acceptance', [LegalConsentController::class, 'accept'])->middleware('auth')->name('legal.accept');
Route::post('/cookie-consent', [CookieConsentController::class, 'store'])->name('cookie-consent.store');
Route::middleware('guest')->group(function () {
    Route::get('/login',  [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);

    Route::get('/forgot-password',  [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');
Route::get('/account/change-password', [AccountPasswordController::class, 'edit'])->middleware('auth')->name('account.password.edit');
Route::put('/account/change-password', [AccountPasswordController::class, 'update'])->middleware('auth')->name('account.password.update');

Route::post('/impersonate-stop', [\App\Http\Controllers\ImpersonationController::class, 'stop'])
    ->middleware('auth')->name('impersonate.stop.global');

Route::get('/files/notes/{note}', [StoredFileController::class, 'note'])
    ->middleware(['auth', 'verified'])->name('files.notes');

/*
|--------------------------------------------------------------------------
| Authenticated Routes — grouped by role
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', \App\Http\Middleware\TrackOnlineUsers::class])->group(function () {

    Route::get('/presence/ping', fn () => response()->noContent())->name('presence.ping');

    Route::get('/billing', \App\Livewire\Billing\SubscriptionStatus::class)->name('billing.index');
    Route::post('/broadcasts/{broadcast}/dismiss', function (\App\Models\PlatformBroadcast $broadcast) {
        $dismissed = session('dismissed_broadcasts', []);
        $dismissed[] = $broadcast->id;
        session(['dismissed_broadcasts' => array_slice(array_unique($dismissed), -20)]);
        return back();
    })->name('broadcasts.dismiss');

    // Administration / school leadership. HODs use the HOD teaching portal,
    // and must not inherit the institution-wide administration portal.
    Route::middleware(['role:school-admin|super-admin|headteacher|principal|deputy-headteacher|deputy'])
        ->prefix('admin')
        ->name('admin.')
        ->group(base_path('routes/admin.php'));

    // Teacher / Class Teacher / HOD
    Route::middleware(['role:school-admin|super-admin|teacher|class-teacher|pre-primary-teacher|lower-primary-teacher|upper-primary-teacher|junior-secondary-teacher|hod|headteacher|principal|deputy-headteacher|deputy'])
        ->prefix('teacher')
        ->name('teacher.')
        ->group(base_path('routes/teacher.php'));

    // Parent / Guardian
    Route::middleware(['role:school-admin|super-admin|parent'])
        ->prefix('parent')
        ->name('parent.')
        ->group(base_path('routes/parent.php'));

    // Finance / Bursar
    Route::middleware(['role:school-admin|super-admin|bursar|headteacher|principal'])
        ->prefix('finance')
        ->name('finance.')
        ->group(base_path('routes/finance.php'));

    Route::middleware(['role:learner'])
        ->prefix('student')
        ->name('student.')
        ->group(base_path('routes/student.php'));
});
