<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:8',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || $user->is_admin || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('Invalid credentials or user is an admin.')],
            ]);
        }

        $user->tokens()->delete();
        $token = $user->createToken('user-auth')->plainTextToken;

        return response()->json([
            'message' => __('Login successful'),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'token' => $token,
        ], 200);
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user->is_admin) {
            return response()->json(['message' => __('Admin users cannot use this endpoint')], 403);
        }

        $request->user()->currentAccessToken()->delete();

        Log::info('User logout', ['user_id' => $user->id, 'email' => $user->email]);

        return response()->json(['message' => __('Logged out successfully')], 200);
    }
}
