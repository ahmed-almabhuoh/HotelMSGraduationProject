<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:191',
                'email' => 'required|email|max:191|unique:users,email',
                'password' => 'required|string|min:8|confirmed',
            ], [
                'name.required' => __('The name field is required.'),
                'email.required' => __('The email field is required.'),
                'email.email' => __('The email must be a valid email address.'),
                'email.unique' => __('The email has already been taken.'),
                'password.required' => __('The password field is required.'),
                'password.min' => __('The password must be at least 8 characters.'),
                'password.confirmed' => __('The password confirmation does not match.'),
            ]);

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'is_admin' => false, // Ensure non-admin
            ]);

            $token = $user->createToken('user-auth')->plainTextToken;

            return response()->json([
                'status' => true,
                'message' => __('Registration successful'),
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'token' => $token,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => config('app.env') == 'local' ? $e->getMessage() : __('Server Error'),
            ], 500);
        }
    }

    public function login(Request $request)
    {
        try {
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
                'status' => true,
                'message' => __('Login successful'),
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'token' => $token,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => config('app.env') == 'local' ? $e->getMessage() : __('Server Error'),
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->is_admin) {
                return response()->json(['message' => __('Admin users cannot use this endpoint')], 403);
            }

            $request->user()->currentAccessToken()->delete();

            return response()->json(['message' => __('Logged out successfully')], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => config('app.env') == 'local' ? $e->getMessage() : __('Server Error'),
            ], 500);
        }
    }
}
