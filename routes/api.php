<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
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

    Route::post('change-password', [AuthController::class, 'changePassword'])->middleware(['throttle:4,1']);
    Route::post('logout', [AuthController::class, 'logout']);
});
