<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RoomBookingController;
use App\Http\Controllers\StripeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Auth
Route::prefix('auth')->middleware(['guest:api', 'throttle:2,1'])->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('forgot-email', [ProfileController::class, 'forgetEmail']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
});

Route::get('/email/verify/{id}/{hash}', [ProfileController::class, 'verifyEmail'])->name('verification.verify');

Route::middleware(['auth:api', 'verified'])->group(function () {
    Route::prefix('profile')->middleware(['throttle:10,1'])->group(function () {
        Route::get('/', [ProfileController::class, 'getProfile']);
        Route::post('/', [ProfileController::class, 'updateProfile']);
    });

    Route::prefix('rooms')->group(function () {
        Route::get('/', [RoomBookingController::class, 'listAvailableRooms']);
        Route::get('{id}', [RoomBookingController::class, 'showRoom']);
        Route::post('{id}/reserve', [RoomBookingController::class, 'reserveRoom']);
    });

    Route::prefix('bookings')->group(function () {
        Route::get('/', [BookingController::class, 'index']);
        Route::get('{bookingReference}', [BookingController::class, 'show']);
        Route::put('{bookingReference}', [BookingController::class, 'update']);
        Route::delete('{bookingReference}', [BookingController::class, 'destroy']);
    });

    Route::post('create-checkout-session', [StripeController::class, 'createCheckoutSession']);

    Route::post('change-password', [AuthController::class, 'changePassword'])->middleware(['throttle:4,1']);
    Route::post('logout', [AuthController::class, 'logout']);
});
