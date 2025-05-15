<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

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
        $updateData = [
            'name' => $data['name'],
            'email' => $data['email'],
            'updated_at' => now(),
        ];

        if (isset($data['mobile'])) {
            $updateData['mobile'] = $data['mobile'];
        }

        if (isset($data['image'])) {
            // Delete old image if exists
            if ($user->image) {
                Storage::disk('public')->delete($user->image);
            }
            $updateData['image'] = $data['image']->store('profile_images', 'public');
        }

        $user->update($updateData);
    }

    public function findUserByName(string $name): ?User
    {
        return User::where('name', $name)->first();
    }
}
