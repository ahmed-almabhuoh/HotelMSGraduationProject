<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // return view('welcome');
    // User::where('email', 'admin@hotel.com')->update([
    //     'password' => Hash::make('password'),
    // ]);
});

Route::view('payment-success', 'payment.success')->name('payment.success');
Route::view('payment-cancelled', 'payment.cancel')->name('payment.cancel');
