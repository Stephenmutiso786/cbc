<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\MpesaController;
use App\Http\Controllers\Api\KemisController;
use App\Http\Controllers\Api\SubscriptionPaymentController;

Route::get('/user', fn(Request $request) => $request->user())->middleware('auth:sanctum');

// M-Pesa callbacks (no auth - called by Safaricom)
Route::prefix('mpesa')->group(function () {
    Route::post('/callback',     [MpesaController::class, 'callback']);
    Route::post('/confirmation', [MpesaController::class, 'confirmation']);
    Route::post('/validation',   [MpesaController::class, 'validation']);
    Route::post('/stk-push',     [MpesaController::class, 'stkPush'])->middleware('auth:sanctum');
});

// Platform subscription billing uses the platform's own M-Pesa account,
// deliberately separate from each school's parent-fee account.
Route::prefix('subscription')->group(function () {
    Route::post('/callback', [SubscriptionPaymentController::class, 'callback']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/stk-push', [SubscriptionPaymentController::class, 'stkPush']);
        Route::get('/status/{paymentId}', [SubscriptionPaymentController::class, 'status']);
    });
});

// KEMIS API
Route::prefix('kemis')->middleware('auth:sanctum')->group(function () {
    Route::post('/sync',         [KemisController::class, 'sync']);
    Route::get('/status',        [KemisController::class, 'status']);
});
