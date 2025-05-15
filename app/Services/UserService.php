<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function updatePassword(User $user, string $newPassword): void
    {
        $user->update([
            'password' => Hash::make($newPassword),
            'password_changed_at' => now(),
        ]);
    }

    public function updateProfile(User $user, array $data): void
    {
        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'updated_at' => now(),
        ]);
    }

    public function findUserByName(string $name): ?User
    {
        return User::where('name', $name)->first();
    }
}
