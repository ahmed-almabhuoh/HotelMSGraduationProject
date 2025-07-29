<?php

use App\Http\Controllers\PaymentController;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // return view('welcome');
    // User::where('email', 'admin@hotel.com')->update([
    //     'password' => Hash::make('password'),
    // ]);
});

// Route::view('payment-success', [PaymentController::class, 'success'])->name('payment.success');
// Route::view('payment-cancelled', [PaymentController::class, 'cancel'])->name('payment.cancel');


Route::get('payment-success', [PaymentController::class, 'success'])->name('payment.success');
Route::get('payment-cancelled', [PaymentController::class, 'cancel'])->name('payment.cancel');
