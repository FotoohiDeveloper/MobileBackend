<?php

use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OtpController;
use App\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\AuthController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\V1\PaymentController;
use App\Http\Controllers\V1\TransportController;
use App\Http\Controllers\V1\SubscriptionController;
use App\Http\Controllers\V1\QRController;
use App\Http\Controllers\V1\RateController;
use App\Http\Controllers\V1\Tourist\TouristAuthController;
use App\Http\Controllers\V1\Tourist\TouristPaymentController;

Route::prefix('v1')->group(function () {

    // User Auth & Identity
    Route::prefix('auth')->group(function () {
        Route::prefix('otp')->group(function () {
            Route::post('/send', [OtpController::class, 'send']);
            Route::post('/verify', [OtpController::class, 'verify']);
            Route::post('/verify-identity', [OtpController::class, 'verifyIdentity']);
        });
    });

    Route::middleware('auth:sanctum')->group(function () {

        // Notifications
        Route::prefix('notifications')->group(function () {
            Route::get('/', [NotificationController::class, 'index']);
            Route::get('/unread', [NotificationController::class, 'unread']);
            Route::patch('/read', [NotificationController::class, 'markAsRead']);
            Route::patch('/read-all', [NotificationController::class, 'markAllAsRead']);
        });

        // Support
        Route::prefix('support')->group(function () {
            Route::get('/tickets', [TicketController::class, 'index']);
            Route::get('/tickets/{id}', [TicketController::class, 'show']);
            Route::post('/tickets', [TicketController::class, 'store']);
            Route::put('/tickets/{id}', [TicketController::class, 'update']);
            Route::post('/tickets/{id}/assign', [TicketController::class, 'assign']);
            Route::post('/tickets/{id}/message', [TicketController::class, 'sendMessage']);
            Route::delete('/tickets/{id}', [TicketController::class, 'destroy']);
            Route::get('/departments', [TicketController::class, 'departments']);
        });

        // Wallet
        Route::prefix('wallet')->group(function () {
            Route::get('/test', [WalletController::class, 'mainReq']);
            Route::get('/', [WalletController::class, 'index']);
            Route::post('/transfer', [WalletController::class, 'transfer']);
            Route::post('/convert', [WalletController::class, 'convert']);
            Route::post('/commit', [WalletController::class, 'commit']);
            Route::post('/commit/increase', [WalletController::class, 'increaseCommitment']);
            Route::post('/commit/consume', [WalletController::class, 'consumeCommitment']);
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
});
