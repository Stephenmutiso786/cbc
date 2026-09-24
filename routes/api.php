<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\MpesaController;
use App\Http\Controllers\Api\KemisController;
use App\Http\Controllers\Api\SubscriptionPaymentController;

// This application uses first-party browser sessions; Sanctum is not
// installed. Apply the web session middleware explicitly for private API
// calls so an unauthenticated request is handled safely instead of throwing
// an undefined-guard exception.
Route::get('/user', fn(Request $request) => $request->user())->middleware(['web', 'auth']);

// M-Pesa callbacks (no auth - called by Safaricom)
Route::prefix('mpesa')->group(function () {
    Route::post('/callback',     [MpesaController::class, 'callback']);
    Route::post('/confirmation', [MpesaController::class, 'confirmation']);
    Route::post('/validation',   [MpesaController::class, 'validation']);
    Route::post('/stk-push',     [MpesaController::class, 'stkPush'])->middleware(['web', 'auth']);
});

// Platform subscription billing uses the platform's own M-Pesa account,
// deliberately separate from each school's parent-fee account.
Route::prefix('subscription')->group(function () {
    Route::post('/callback', [SubscriptionPaymentController::class, 'callback']);
    Route::middleware(['web', 'auth'])->group(function () {
        Route::post('/stk-push', [SubscriptionPaymentController::class, 'stkPush']);
        Route::get('/status/{paymentId}', [SubscriptionPaymentController::class, 'status']);
        Route::post('/sms-stk-push', [SubscriptionPaymentController::class, 'smsStkPush']);
        Route::get('/sms-status/{orderId}', [SubscriptionPaymentController::class, 'smsStatus']);
    });
});

// KEMIS API
Route::prefix('kemis')->middleware(['web', 'auth'])->group(function () {
    Route::post('/sync',         [KemisController::class, 'sync']);
    Route::get('/status',        [KemisController::class, 'status']);
});
