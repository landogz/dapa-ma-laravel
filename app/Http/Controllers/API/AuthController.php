<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Services\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class AuthController extends Controller
{
    public function __construct(
        private readonly ProfileService $profileService,
    ) {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $isFirstAdmin = ! User::query()->where('role', 'super_admin')->exists();
        $fullName = trim($request->validated('name'));
        $nameParts = preg_split('/\s+/', $fullName, 2);

        $user = User::query()->create([
            'name' => $fullName,
            'first_name' => $nameParts[0] ?? $fullName,
            'last_name' => $nameParts[1] ?? '',
            'email' => $request->validated('email'),
            'password' => Hash::make($request->validated('password')),
            'role' => $isFirstAdmin ? 'super_admin' : 'app_user',
            'last_login_at' => now(),
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
                'user' => $this->profileService->formatUser($user),
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
        $user->forceFill(['last_login_at' => now()])->save();

        $token = $user->createToken('api-token', ['*'], now()->addDays(30))->plainTextToken;

        return response()->json([
            'status'  => true,
            'message' => 'Login successful.',
            'data'    => [
                'token' => $token,
                'user'  => $this->profileService->formatUser($user),
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
        return response()->json([
            'status' => true,
            'data'   => $this->profileService->formatUser($request->user()),
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->profileService->update(
            $request->user(),
            array_merge(
                $request->validated(),
                [
                    'profile_photo' => $request->file('profile_photo'),
                    'remove_profile_photo' => $request->boolean('remove_profile_photo'),
                ],
            ),
        );

        return response()->json([
            'status'  => true,
            'message' => 'Profile updated.',
            'data'    => $this->profileService->formatUser($user),
        ]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! Hash::check($request->input('current_password'), $user->password)) {
            return response()->json([
                'status' => false,
                'message' => 'Current password is incorrect.',
                'errors' => [
                    'current_password' => ['Current password is incorrect.'],
                ],
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
