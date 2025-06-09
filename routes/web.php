<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // return view('welcome');
    User::where('email', 'admin@hotel.com')->update([
        'password' => Hash::make('password'),
    ]);
});
