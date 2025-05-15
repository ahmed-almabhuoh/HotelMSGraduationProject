<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password as RulesPassword;
use Illuminate\Validation\ValidationException;
// use Illuminate\Validation\Rules\Password as PasswordValidation;

class AuthController extends Controller
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

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

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|max:191',
        ], [
            'email.required' => __('The email field is required.'),
            'email.email' => __('The email must be a valid email address.'),
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || $user->is_admin) {
            throw ValidationException::withMessages([
                'email' => [__('Invalid email or user is an admin.')],
            ]);
        }

        // Generate a raw token
        $token = \Illuminate\Support\Str::random(64);

        // Store hashed token in password_reset_tokens
        \Illuminate\Support\Facades\DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        // Trigger notification
        $user->notify(new \App\Notifications\ResetPassword($token));

        return response()->json([
            'message' => __('Password reset token generated'),
            'token' => $token, // For API testing; remove in production with email
        ], 200);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|max:191',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ], [
            'email.required' => __('The email field is required.'),
            'email.email' => __('The email must be a valid email address.'),
            'token.required' => __('The token field is required.'),
            'password.required' => __('The password field is required.'),
            'password.min' => __('The password must be at least 8 characters.'),
            'password.confirmed' => __('The password confirmation does not match.'),
        ]);

        $status = Password::reset(
            $request->only('email', 'token', 'password', 'password_confirmation'),
            function ($user, $password) {
                if ($user->is_admin) {
                    throw ValidationException::withMessages([
                        'email' => [__('Invalid email or user is an admin.')],
                    ]);
                }
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();
                $user->tokens()->delete();
                Log::info('Password reset', ['user_id' => $user->id, 'email' => $user->email, 'locale' => app()->getLocale()]);
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'status' => true,
                'message' => __('Password reset successful'),
            ], 200);
        }

        throw ValidationException::withMessages([
            'email' => [__('Invalid token or email.')],
        ]);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => [
                'required',
                'string',
                RulesPassword::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised(),
                'confirmed'
            ],
        ], [
            'current_password.required' => __('The current password field is required.'),
            'password.required' => __('The new password field is required.'),
            'password.min' => __('The new password must be at least 8 characters.'),
            'password.confirmed' => __('The new password confirmation does not match.'),
            'password.mixed_case' => __('The new password must contain both uppercase and lowercase letters.'),
            'password.numbers' => __('The new password must contain at least one number.'),
            'password.symbols' => __('The new password must contain at least one special character.'),
            'password.uncompromised' => __('The new password has been compromised in a data breach.'),
        ]);

        try {
            $user = auth()->user();

            // Verify current password
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'status' => false,
                    'message' => __('Current password is incorrect.'),
                    'errors' => ['current_password' => __('Current password is incorrect.')]
                ], 422);
            }

            DB::beginTransaction();

            $this->userService->updatePassword($user, $request->password);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => __('Password changed successfully.'),
                'data' => null
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => config('app.env') === 'local' ? $e->getMessage() : __('An error occurred while changing the password.'),
                'errors' => []
            ], 500);
        }
    }
}
