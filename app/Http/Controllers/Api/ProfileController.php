<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\UserResource;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class ProfileController extends Controller
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
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
}
