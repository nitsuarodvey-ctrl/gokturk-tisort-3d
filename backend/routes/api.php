<?php

use App\Http\Controllers\Internal\AuthController;
use App\Http\Controllers\Internal\OrderController;
use App\Http\Controllers\Internal\PaymentController;
use App\Http\Controllers\PaymentCallbackController;
use App\Http\Middleware\RequireAdminSession;
use App\Http\Middleware\VerifyInternalApiKey;
use Illuminate\Support\Facades\Route;

Route::prefix('internal/v1')->middleware(VerifyInternalApiKey::class)->group(function () {
    Route::post('/orders', [OrderController::class, 'store']);
    Route::post('/payments', [PaymentController::class, 'store']);
    Route::post('/admin/login', [AuthController::class, 'login']);

    Route::middleware(RequireAdminSession::class)->group(function () {
        Route::get('/admin/session', [AuthController::class, 'session']);
        Route::post('/admin/logout', [AuthController::class, 'logout']);
        Route::apiResource('orders', OrderController::class)->except(['store']);
    });
});

Route::post('/v1/payments/kuveyt-turk/callback', [PaymentCallbackController::class, 'store'])
    ->middleware('throttle:120,1');
