<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class ProfileController extends Controller
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function forgetEmail(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:45', 'regex:/^[\p{L}\s-]+$/u'],
            'password' => ['required', 'string'],
        ], [
            'name.required' => __('The name field is required.'),
            'name.regex' => __('The name may only contain letters, spaces, and hyphens.'),
            'password.required' => __('The password field is required.'),
        ]);

        try {
            $user = $this->userService->findUserByName($request->name);

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'status' => false,
                    'message' => __('Invalid credentials provided.'),
                    'errors' => ['credentials' => __('The provided name or password is incorrect.')]
                ], 422);
            }

            return response()->json([
                'status' => true,
                'message' => __('Your email is:') . ' ' . $user->email,
                'data' => null
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => config('app.env') === 'local' ? $e->getMessage() : __('An error occurred while processing the password reset request.'),
                'errors' => []
            ], 500);
        }
    }

    public function getProfile()
    {
        try {
            $user = auth()->user();

            return response()->json([
                'status' => true,
                'message' => __('Profile retrieved successfully'),
                'data' => new UserResource($user),
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => config('app.env') === 'local' ? $e->getMessage() : __('An error occurred while retrieving the profile.'),
                'errors' => []
            ], 500);
        }
    }

    public function updateProfile(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:45', 'regex:/^[\p{L}\s-]+$/u'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . auth()->id()],
        ], [
            'name.required' => __('The name field is required.'),
            'name.regex' => __('The name may only contain letters, spaces, and hyphens.'),
            'email.required' => __('The email field is required.'),
            'email.email' => __('The email must be a valid email address.'),
            'email.unique' => __('The email has already been taken.'),
        ]);

        try {
            $user = auth()->user();

            DB::beginTransaction();

            $this->userService->updateProfile($user, [
                'name' => $request->name,
                'email' => $request->email,
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => __('Profile updated successfully'),
                'data' => new UserResource($user->fresh()),
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => config('app.env') === 'local' ? $e->getMessage() : __('An error occurred while updating the profile.'),
                'errors' => []
            ], 500);
        }
    }

    public function verifyEmail(Request $request, $id, $hash)
    {
        try {
            $user = User::findOrFail($id);

            if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
                return response()->json([
                    'status' => false,
                    'message' => __('Invalid verification link.'),
                    'errors' => ['verification' => __('The verification link is invalid.')],
                ], 422);
            }

            if ($user->hasVerifiedEmail()) {
                return response()->json([
                    'status' => true,
                    'message' => __('Email already verified.'),
                    'data' => new UserResource($user),
                ], 200);
            }

            if ($user->markEmailAsVerified()) {
                event(new Verified($user));
            }

            Log::info('Email verified successfully for user ID: ' . $user->id, [
                'email' => $user->email,
            ]);

            return response()->json([
                'status' => true,
                'message' => __('Email verified successfully.'),
                'data' => new UserResource($user),
            ], 200);
        } catch (Exception $e) {
            Log::error('Email verification failed for user ID: ' . $id, [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => false,
                'message' => config('app.env') === 'local' ? $e->getMessage() : __('An error occurred during email verification.'),
                'errors' => [],
            ], 500);
        }
    }
}
