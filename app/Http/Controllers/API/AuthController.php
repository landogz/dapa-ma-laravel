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
        $isFirstAdmin = ! User::query()->where('role', 'super_admin')->exists();

        $user = User::query()->create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => Hash::make($request->validated('password')),
            'role' => $isFirstAdmin ? 'super_admin' : 'app_user',
        ]);

        $token = $user->createToken(
            $isFirstAdmin ? 'admin-setup-token' : 'api-token',
            ['*'],
            now()->addDays(30)
        )->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => $isFirstAdmin
                ? 'Administrator account created successfully.'
                : 'Account created successfully.',
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'profile_image_url' => $this->userProfileImageUrl($user),
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

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $user = $request->user();
        if (! Hash::check($request->input('current_password'), $user->password)) {
            return response()->json([
                'status'  => false,
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->input('password')),
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Password updated successfully.',
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
