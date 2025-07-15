<?php

namespace App\Models;

use App\Notifications\ResetPassword; // Your custom notification for password reset
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Contracts\Auth\MustVerifyEmail; // ADD THIS LINE

class User extends Authenticatable implements FilamentUser, MustVerifyEmail // ADD MustVerifyEmail here
{
    use Notifiable, HasApiTokens; // Removed duplicate Notifiable

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'mobile',
        'image',
        'email_verified_at'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_admin' => 'boolean',
    ];

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_admin;
    }

    /**
     * Send the password reset notification.
     * This method is already defined in your User model.
     * We just need to make sure your App\Notifications\ResetPassword
     * correctly builds the frontend URL.
     *
     * @param  string  $token
     * @return void
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPassword($token));
    }

    /**
     * Send the email verification notification.
     * This method is part of the MustVerifyEmail contract.
     * Laravel's default VerifyEmail notification will be used unless you override it.
     * We will customize its URL generation in the controller.
     *
     * @return void
     */
    // You don't need to define sendEmailVerificationNotification here if you're using default Laravel behavior,
    // but ensure MustVerifyEmail is implemented. The AuthApiController will adjust the URL.
}
