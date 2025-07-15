<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RoomBookingController;
use App\Http\Controllers\StripeController;
use App\Http\Controllers\StripeWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Auth\Events\Verified; // Import for the verification.verify route

// Auth
Route::prefix('auth')->middleware(['guest:api', 'throttle:5,1'])->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    // Password Reset Endpoints (Code-based flow)
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']); // Sends the code
    Route::post('verify-password-reset-code', [AuthController::class, 'verifyPasswordResetCode']); // NEW: Verifies the code
    Route::post('reset-password', [AuthController::class, 'resetPassword']); // Resets password after code verification

    // Existing 'forgot-email' (keep if it has a specific purpose)
    Route::post('forgot-email', [ProfileController::class, 'forgetEmail']);

    // Resend Email Verification Endpoint (for unverified guests)
    Route::post('email/resend-verification', [AuthController::class, 'resendVerification']);
});

// Email Verification Route (GET request from email link)
Route::get('/email/verify/{id}/{hash}', [ProfileController::class, 'verifyEmail'])->name('verification.verify');

// Authenticated and Verified User Routes
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
    Route::post('stripe/webhook', [StripeWebhookController::class, 'handle']);

    Route::post('change-password', [AuthController::class, 'changePassword'])->middleware(['throttle:4,1']);
    Route::post('logout', [AuthController::class, 'logout']);

    // Endpoint to trigger sending verification email for an authenticated user
    Route::post('/email/verification-notification', function (Request $request) {
        if (!$request->user()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.'], 400);
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->json(['message' => 'Verification link sent!'], 200);
    })->middleware('throttle:6,1')->name('verification.send');
});

// Stripe webhook (outside auth middleware)
// Route::post('stripe/webhook', [StripeWebhookController::class, 'handle']); // Already present
