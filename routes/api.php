<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\WeekController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\CronController;
use App\Http\Controllers\PushTokenController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Authentication Routes (Public)
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// Cron Routes (Public but secured with CRON_SECRET)
Route::post('/cron/process-weekly-cycle', [CronController::class, 'processWeeklyCycle']);
Route::post('/cron/payment-reminder', [CronController::class, 'sendPaymentReminders']);

// Protected Routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Week Information
    Route::get('/week/current', [WeekController::class, 'getCurrentWeek']);
    Route::get('/available-eggs', [WeekController::class, 'getAvailableEggs']);
    
    // App Settings
    Route::get('/settings', [SettingsController::class, 'index']);

    // Orders (Customer)
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/unpaid', [OrderController::class, 'getUnpaidOrders']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/current-week', [OrderController::class, 'getCurrentWeekOrder']);
    Route::get('/orders/current-week-subscription', [OrderController::class, 'getCurrentWeekSubscriptionOrder']);
    Route::put('/orders/{id}', [OrderController::class, 'update']);
    Route::delete('/orders/{id}', [OrderController::class, 'destroy']);
    Route::post('/orders/{id}/mark-payment-submitted', [OrderController::class, 'markPaymentSubmitted']);
    Route::post('/orders/{id}/confirm-pickup', [OrderController::class, 'confirmPickup']);

    // Subscriptions (Customer)
    Route::get('/subscriptions/current', [SubscriptionController::class, 'getCurrent']);
    Route::get('/subscriptions/availability', [SubscriptionController::class, 'getAvailability']);
    Route::post('/subscriptions', [SubscriptionController::class, 'store']);
    Route::delete('/subscriptions/{id}', [SubscriptionController::class, 'destroy']);

    // Push Notifications
    Route::post('/push-token', [PushTokenController::class, 'store']);
    Route::delete('/push-token', [PushTokenController::class, 'destroy']);

    // Admin Routes
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/orders', [AdminController::class, 'getAllOrders']);
        Route::get('/subscriptions', [AdminController::class, 'getAllSubscriptions']);
        Route::get('/users', [AdminController::class, 'getAllUsers']);
        Route::get('/users/{id}', [AdminController::class, 'getUserDetails']);
        Route::put('/orders/{id}/confirm-payment', [AdminController::class, 'confirmPayment']);
        Route::post('/orders/mark-delivered', [AdminController::class, 'markAllOrdersDelivered']);
        Route::put('/week/current', [WeekController::class, 'updateCurrentWeek']);
        Route::post('/week/subscription-preview', [WeekController::class, 'getSubscriptionPreview']);
        Route::put('/settings/price', [SettingsController::class, 'updatePrice']);
        Route::get('/settings/payment', [AdminController::class, 'getPaymentSettings']);
        Route::put('/settings/payment', [AdminController::class, 'updatePaymentSettings']);
    });
});

