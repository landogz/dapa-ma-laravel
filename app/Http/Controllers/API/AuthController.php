<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        if (User::query()->where('role', 'super_admin')->exists()) {
            return response()->json([
                'status' => false,
                'message' => 'Admin registration is disabled. Ask a super administrator to create your account.',
            ], 403);
        }

        $user = User::query()->create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => Hash::make($request->validated('password')),
            'role' => 'super_admin',
        ]);

        $token = $user->createToken('admin-setup-token', ['*'], now()->addDays(30))->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => 'Administrator account created successfully.',
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ],
            ],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'status'  => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        $user  = $request->user();
        $token = $user->createToken('api-token', ['*'], now()->addDays(30))->plainTextToken;

        return response()->json([
            'status'  => true,
            'message' => 'Login successful.',
            'data'    => [
                'token' => $token,
                'user'  => [
                    'id'                 => $user->id,
                    'name'               => $user->name,
                    'email'              => $user->email,
                    'role'               => $user->role,
                    'profile_image_url'  => $this->userProfileImageUrl($user),
                ],
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Logged out successfully.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'status' => true,
            'data'   => [
                'id'                 => $user->id,
                'name'               => $user->name,
                'email'              => $user->email,
                'role'               => $user->role,
                'profile_image_url'  => $this->userProfileImageUrl($user),
            ],
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $request->validate([
            'name'         => ['sometimes', 'string', 'max:255'],
            'profile_photo' => ['sometimes', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
        ]);

        $payload = [];
        if ($request->has('name')) {
            $payload['name'] = $request->input('name');
        }
        if ($request->hasFile('profile_photo')) {
            if ($user->profile_image_url) {
                Storage::disk('public')->delete($user->profile_image_url);
            }
            $path = $request->file('profile_photo')->store('profiles', 'public');
            $payload['profile_image_url'] = $path;
        }
        if ($payload !== []) {
            $user->update($payload);
        }

        return response()->json([
            'status'  => true,
            'message' => 'Profile updated.',
            'data'    => [
                'id'                 => $user->id,
                'name'               => $user->name,
                'email'              => $user->email,
                'role'               => $user->role,
                'profile_image_url'  => $this->userProfileImageUrl($user->fresh()),
            ],
        ]);
    }

    private function userProfileImageUrl(User $user): ?string
    {
        if (! $user->profile_image_url) {
            return null;
        }
        if (str_starts_with($user->profile_image_url, 'http')) {
            return $user->profile_image_url;
        }
        return Storage::disk('public')->url($user->profile_image_url);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::broker()->sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'status'  => true,
                'message' => __($status),
            ]);
        }

        return response()->json([
            'status'  => false,
            'message' => __($status),
        ], 422);
    }
}
