<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\AuthController;
use App\Http\Controllers\V1\WalletController;
use App\Http\Controllers\V1\PaymentController;
use App\Http\Controllers\V1\TransportController;
use App\Http\Controllers\V1\SubscriptionController;
use App\Http\Controllers\V1\QRController;
use App\Http\Controllers\V1\RateController;
use App\Http\Controllers\V1\Tourist\TouristAuthController;
use App\Http\Controllers\V1\Tourist\TouristPaymentController;

Route::prefix('v1')->group(function() {

    // Auth & Identity
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class,'register'])->name('api.register');
        Route::post('login', [AuthController::class,'login'])->name('api.login');
        Route::post('verify-code', [AuthController::class,'verifyCode'])->name('api.verify-code');
        Route::post('logout', [AuthController::class,'logout'])->name('api.logout');
        Route::get('me', [AuthController::class,'me'])->name('api.me');
        Route::post('refresh-token', [AuthController::class,'refreshToken'])->name('api.refresh-token');
    });

    // Wallet
    Route::prefix('wallet')->group(function () {
        Route::get('/', [WalletController::class,'show'])->name('api.wallet');
        Route::get('/{id}/transactions', [WalletController::class,'transactions'])->name('api.my-transactions');
        Route::post('/topup', [WalletController::class,'topup'])->name('api.topup');
        Route::post('/convert', [WalletController::class,'convert'])->name('api.wallet.convert');
    });

    // Payment
    Route::prefix('payment')->group(function () {
        Route::post('/initiate', [PaymentController::class, 'initiate'])->name('api.payment.initiate');
        Route::post('/verify', [PaymentController::class, 'verify'])->name('api.payment.verify');
        Route::get('/history', [PaymentController::class, 'history'])->name('api.payment.history');
    });

    // Exchange Rate
    Route::get('/rate/usd', [RateController::class, 'usd'])->name('api.rate.usd');

    // Transport
    Route::prefix('transport')->group(function () {
        Route::get('/routes', [TransportController::class, 'routes'])->name('api.transport.routes');
        Route::get('/stations', [TransportController::class, 'stations'])->name('api.transport.stations');
        Route::get('/nearby', [TransportController::class, 'nearby'])->name('api.transport.nearby');
        Route::post('/ride/start', [TransportController::class, 'startRide'])->name('api.transport.ride.start');
        Route::post('/ride/end', [TransportController::class, 'endRide'])->name('api.transport.ride.end');
        Route::get('/ride/history', [TransportController::class, 'history'])->name('api.transport.ride.history');
    });

    // QR
    Route::prefix('qr')->group(function () {
        Route::post('/generate', [QRController::class, 'generate'])->name('api.qr.generate');
        Route::post('/scan', [QRController::class, 'scan'])->name('api.qr.scan');
    });

    // Subscriptions
    Route::prefix('subscriptions')->group(function () {
        Route::get('/', [SubscriptionController::class, 'index'])->name('api.subscriptions');
        Route::get('/my', [SubscriptionController::class, 'my'])->name('api.subscriptions.my');
        Route::post('/purchase', [SubscriptionController::class, 'purchase'])->name('api.subscriptions.purchase');
    });

    // Tourist-specific
    Route::prefix('tourist')->group(function () {
        Route::post('auth/register', [TouristAuthController::class, 'register'])->name('api.tourist.register');
        Route::post('card/activate', [TouristAuthController::class, 'activateCard'])->name('api.tourist.card.activate');
        Route::post('payment/preview', [TouristPaymentController::class, 'preview'])->name('api.tourist.payment.preview');
        Route::post('payment/pay', [TouristPaymentController::class, 'pay'])->name('api.tourist.payment.pay');
    });
});
