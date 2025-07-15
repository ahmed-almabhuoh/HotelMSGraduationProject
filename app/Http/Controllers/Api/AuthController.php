<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use App\Services\UserService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\VerifyPasswordResetCodeRequest; // NEW
use App\Http\Requests\ResetPasswordWithVerificationTokenRequest; // NEW
use Carbon\Carbon;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache; // For temporary verification token
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
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

            // Send email verification notification
            $user->notify(new VerifyEmailNotification());

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

    // public function forgotPassword(Request $request)
    // {
    //     $request->validate([
    //         'email' => 'required|email|max:191',
    //     ], [
    //         'email.required' => __('The email field is required.'),
    //         'email.email' => __('The email must be a valid email address.'),
    //     ]);

    //     $user = User::where('email', $request->email)->first();

    //     if (!$user || $user->is_admin) {
    //         throw ValidationException::withMessages([
    //             'email' => [__('Invalid email or user is an admin.')],
    //         ]);
    //     }

    //     // Generate a raw token
    //     $token = \Illuminate\Support\Str::random(64);

    //     // Store hashed token in password_reset_tokens
    //     \Illuminate\Support\Facades\DB::table('password_reset_tokens')->updateOrInsert(
    //         ['email' => $request->email],
    //         [
    //             'token' => Hash::make($token),
    //             'created_at' => now(),
    //         ]
    //     );

    //     // Trigger notification
    //     $user->notify(new \App\Notifications\ResetPassword($token));

    //     return response()->json([
    //         'message' => __('Password reset token generated'),
    //         'token' => $token, // For API testing; remove in production with email
    //     ], 200);
    // }

    /**
     * Request a password reset code via email.
     *
     * @param  \App\Http\Requests\ForgotPasswordRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || $user->is_admin) { // Assuming is_admin check is still desired
            return response()->json(['message' => __('Invalid email or user is an admin.')], 400);
        }

        // Generate a 6-digit numeric code
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store hashed code in password_reset_tokens table
        // This table will now store the HASHED numeric code
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'token' => Hash::make($code), // Store the HASHED code
                'created_at' => now(),
            ]
        );

        // Trigger notification to send the RAW code
        $user->notify(new \App\Notifications\ResetPassword($code));

        return response()->json([
            'message' => __('A password reset code has been sent to your email.'),
        ], 200);
    }

    /**
     * Verify the password reset code sent to the user's email.
     *
     * @param  \App\Http\Requests\VerifyPasswordResetCodeRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyPasswordResetCode(VerifyPasswordResetCodeRequest $request): JsonResponse
    {
        $passwordReset = DB::table('password_reset_tokens')
                            ->where('email', $request->email)
                            ->first();

        if (!$passwordReset || !Hash::check($request->code, $passwordReset->token)) {
            return response()->json(['message' => __('Invalid verification code.')], 400);
        }

        // Check if the code has expired (default is 60 minutes, configured in auth.php)
        $tokenLifetime = config('auth.passwords.users.expire'); // In minutes
        if (Carbon::parse($passwordReset->created_at)->addMinutes($tokenLifetime)->isPast()) {
            // Delete the expired code
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json(['message' => __('The verification code has expired.')], 400);
        }

        // Code is valid and not expired.
        // Generate a temporary verification token for the actual password reset step.
        $verificationToken = Str::random(32);
        $cacheKey = 'password_reset_verification_' . $request->email;
        // Store this token in cache for a short period (e.g., 10 minutes)
        Cache::put($cacheKey, $verificationToken, now()->addMinutes(10));

        // Delete the used code from the database to prevent reuse
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json([
            'message' => __('Code verified successfully. You can now reset your password.'),
            'verification_token' => $verificationToken, // Send this back to the frontend
        ], 200);
    }

    /**
     * Reset the given user's password using the verification token.
     * This endpoint is hit by the frontend AFTER the code has been verified.
     *
     * @param  \App\Http\Requests\ResetPasswordWithVerificationTokenRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetPassword(ResetPasswordWithVerificationTokenRequest $request): JsonResponse
    {
        $cacheKey = 'password_reset_verification_' . $request->email;
        $storedVerificationToken = Cache::get($cacheKey);

        if (!$storedVerificationToken || $storedVerificationToken !== $request->verification_token) {
            return response()->json(['message' => __('Invalid or expired verification session.')], 400);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => __('User not found.')], 404);
        }

        // Update the user's password
        $user->password = Hash::make($request->password);
        $user->save();

        // Invalidate the verification token from cache
        Cache::forget($cacheKey);

        return response()->json(['message' => __('Password has been reset successfully!')], 200);
    }

    /**
     * Resend the email verification notification.
     * (This method remains the same as in previous responses)
     *
     * @param  \App\Http\Requests\ForgotPasswordRequest  $request (re-using for email validation)
     * @return \Illuminate\Http\JsonResponse
     */
    public function resendVerification(ForgotPasswordRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Your email is already verified!'], 400);
        }

        try {
            // Generate a temporary signed URL for verification
            $verificationUrl = URL::temporarySignedRoute(
                'verification.verify',
                Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
                [
                    'id' => $user->getKey(),
                    'hash' => sha1($user->getEmailForVerification()),
                ]
            );

            // Modify the URL to point to your frontend application
            $frontendVerificationUrl = env('FRONTEND_URL') . '/verify-email?' . parse_url($verificationUrl, PHP_URL_QUERY);

            // Send the notification, overriding the URL
            $user->notify(new class($frontendVerificationUrl) extends VerifyEmail {
                protected $frontendUrl;

                public function __construct($frontendUrl)
                {
                    $this->frontendUrl = $frontendUrl;
                }

                protected function verificationUrl($notifiable)
                {
                    return $this->frontendUrl;
                }
            });

            return response()->json(['message' => 'Verification link sent to your email!'], 200);
        } catch (\Exception $e) {
            \Log::error('Failed to send verification email: ' . $e->getMessage());
            return response()->json(['message' => 'Could not send verification email. Please try again later.'], 500);
        }
    }


    // public function resetPassword(Request $request)
    // {
    //     $request->validate([
    //         'email' => 'required|email|max:191',
    //         'token' => 'required|string',
    //         'password' => 'required|string|min:8|confirmed',
    //     ], [
    //         'email.required' => __('The email field is required.'),
    //         'email.email' => __('The email must be a valid email address.'),
    //         'token.required' => __('The token field is required.'),
    //         'password.required' => __('The password field is required.'),
    //         'password.min' => __('The password must be at least 8 characters.'),
    //         'password.confirmed' => __('The password confirmation does not match.'),
    //     ]);

    //     $status = Password::reset(
    //         $request->only('email', 'token', 'password', 'password_confirmation'),
    //         function ($user, $password) {
    //             if ($user->is_admin) {
    //                 throw ValidationException::withMessages([
    //                     'email' => [__('Invalid email or user is an admin.')],
    //                 ]);
    //             }
    //             $user->forceFill([
    //                 'password' => Hash::make($password),
    //             ])->save();
    //             $user->tokens()->delete();
    //             Log::info('Password reset', ['user_id' => $user->id, 'email' => $user->email, 'locale' => app()->getLocale()]);
    //         }
    //     );

    //     if ($status === Password::PASSWORD_RESET) {
    //         return response()->json([
    //             'status' => true,
    //             'message' => __('Password reset successful'),
    //         ], 200);
    //     }

    //     throw ValidationException::withMessages([
    //         'email' => [__('Invalid token or email.')],
    //     ]);
    // }

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
