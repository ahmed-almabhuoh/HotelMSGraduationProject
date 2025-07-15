<?php

namespace App\Http\Controllers;

use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail; // For email verification
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password; // Use Laravel's Password broker
use Illuminate\Support\Facades\Lang;

class AuthApiController extends Controller
{
    /**
     * Handle a password reset link request for the API.
     * This will trigger the sendPasswordResetNotification on your User model.
     *
     * @param  \App\Http\Requests\ForgotPasswordRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $response = Password::broker()->sendResetLink(
            $request->only('email')
        );

        if ($response == Password::RESET_LINK_SENT) {
            return response()->json(['message' => Lang::get($response)], 200);
        }

        // If the email is not found, or other errors, Laravel returns a specific status.
        return response()->json(['message' => Lang::get($response)], 400);
    }

    /**
     * Reset the given user's password.
     * This endpoint is hit by the frontend after the user clicks the reset link and submits a new password.
     *
     * @param  \App\Http\Requests\ResetPasswordRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $response = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                // You might want to log the user in here if it's an authenticated API flow
                // auth()->login($user);
            }
        );

        if ($response == Password::PASSWORD_RESET) {
            return response()->json(['message' => Lang::get($response)], 200);
        }

        return response()->json(['message' => Lang::get($response)], 400);
    }

    /**
     * Resend the email verification notification.
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
            // Laravel's default email verification notification sends a signed URL.
            // We need to replicate that for our API, but point it to our frontend.
            // The 'verification.verify' route is a built-in Laravel route that handles verification.
            $verificationUrl = URL::temporarySignedRoute(
                'verification.verify',
                Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)), // Expiration time
                [
                    'id' => $user->getKey(),
                    'hash' => sha1($user->getEmailForVerification()),
                ]
            );

            // Now, we need to modify this URL to point to your frontend application.
            // Your frontend should have a route like /verify-email that accepts the query parameters.
            $frontendVerificationUrl = env('FRONTEND_URL') . '/verify-email?' . parse_url($verificationUrl, PHP_URL_QUERY);

            // Send the notification using the built-in Laravel notification
            // We'll override the URL generation to use our frontend URL.
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
}
