<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\WeeklyStockController;
use App\Http\Controllers\AdminController;
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

// Protected Routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Weekly Stock
    Route::get('/weekly-stock', [WeeklyStockController::class, 'getCurrentWeek']);
    Route::get('/available-eggs', [WeeklyStockController::class, 'getAvailableEggs']);

    // Orders (Customer)
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/current-week', [OrderController::class, 'getCurrentWeekOrder']);
    Route::put('/orders/{id}', [OrderController::class, 'update']);
    Route::delete('/orders/{id}', [OrderController::class, 'destroy']);

    // Subscriptions (Customer)
    Route::get('/subscriptions/current', [SubscriptionController::class, 'getCurrent']);
    Route::post('/subscriptions', [SubscriptionController::class, 'store']);
    Route::delete('/subscriptions/{id}', [SubscriptionController::class, 'destroy']);

    // Admin Routes
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/orders', [AdminController::class, 'getAllOrders']);
        Route::get('/subscriptions', [AdminController::class, 'getAllSubscriptions']);
        Route::put('/weekly-stock', [AdminController::class, 'updateWeeklyStock']);
        Route::put('/delivery-info', [AdminController::class, 'updateDeliveryInfo']);
        Route::post('/orders/mark-delivered', [AdminController::class, 'markAllOrdersDelivered']);
        Route::put('/orders/{id}/approve', [AdminController::class, 'approveOrder']);
        Route::put('/orders/{id}/decline', [AdminController::class, 'declineOrder']);
    });
});

