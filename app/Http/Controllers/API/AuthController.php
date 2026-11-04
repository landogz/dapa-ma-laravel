<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\BootstrapAdminRequest;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\CheckEmailRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\VerifyLoginCodeRequest;
use App\Http\Requests\Auth\VerifyResetCodeRequest;
use App\Http\Requests\Auth\UpdateOnboardingRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\AccessTokenService;
use App\Services\Auth\EmailCheckService;
use App\Services\LoginVerificationService;
use App\Services\NotificationService;
use App\Services\Password\PasswordService;
use App\Services\PasswordResetService;
use App\Services\ProfileService;
use App\Support\Auth\AdminGateCookie;
use App\Support\Auth\AdminRoleCookie;
use App\Support\Auth\AdminRoleSections;
use App\Support\Auth\LoginOtp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(
        private readonly ProfileService $profileService,
        private readonly NotificationService $notificationService,
        private readonly PasswordResetService $passwordResetService,
        private readonly LoginVerificationService $loginVerificationService,
        private readonly EmailCheckService $emailCheckService,
        private readonly PasswordService $passwordService,
        private readonly AccessTokenService $accessTokenService,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function checkEmail(CheckEmailRequest $request): JsonResponse
    {
        $result = $this->emailCheckService->availability($request->validated('email'));

        return response()->json([
            'status' => true,
            'message' => $result['available']
                ? 'Email is available.'
                : 'This email is already registered.',
            'data' => $result,
        ]);
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->createAccount($request->validated(), 'app_user');
        $token = $this->accessTokenService->issueFull($user);

        return response()->json([
            'status' => true,
            'message' => 'Account created successfully.',
            'data' => [
                'token' => $token,
                'user' => $this->profileService->formatUser($user),
            ],
        ], 201);
    }

    public function bootstrapAdmin(BootstrapAdminRequest $request): JsonResponse
    {
        $user = $this->createAccount($request->validated(), 'super_admin');
        $token = $this->accessTokenService->issueFull($user, 'admin-setup-token');

        return $this->withAdminGate(response()->json([
            'status' => true,
            'message' => 'Administrator account created successfully.',
            'data' => [
                'token' => $token,
                'user' => $this->profileService->formatUser($user),
            ],
        ], 201), $user);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $email = (string) $request->input('email');

        $user = User::query()->where('email', $email)->first();

        if (! $user || ! Hash::check((string) $request->input('password'), $user->password)) {
            $this->auditLogger->record([
                'actor' => [
                    'id' => null,
                    'name' => null,
                    'email' => $email,
                    'role' => null,
                ],
                'category' => 'auth',
                'action' => 'login_failed',
                'description' => "Failed sign-in attempt for {$email}",
                'subject_label' => $email,
            ], $request);

            return response()->json([
                'status'  => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        if ($user->is_active === false) {
            $this->auditLogger->record([
                'actor' => $user,
                'category' => 'auth',
                'action' => 'login_failed',
                'description' => "Blocked sign-in for suspended account {$user->email}",
                'subject_type' => $user->getMorphClass(),
                'subject_id' => $user->id,
                'subject_label' => $user->email,
            ], $request);

            return response()->json([
                'status' => false,
                'message' => 'Your account has been suspended. Contact an administrator.',
                'data' => [
                    'is_active' => false,
                ],
            ], 403);
        }

        if (! LoginOtp::required()) {
            return $this->completeVerifiedLogin($user, $request, 'Signed in.');
        }

        $verification = $this->loginVerificationService->sendCode($user);
        if (! $verification['status']) {
            return response()->json([
                'status' => false,
                'message' => $verification['message'],
            ], $verification['http']);
        }

        $token = $this->accessTokenService->issuePending($user);

        return response()->json([
            'status'  => true,
            'message' => 'Login successful. Check your email for a 6-digit verification code.',
            'data'    => [
                'token' => $token,
                'user'  => $this->profileService->formatUser($user),
                'requires_verification' => true,
            ],
        ]);
    }

    public function sendLoginCode(Request $request): JsonResponse
    {
        $result = $this->loginVerificationService->sendCode($request->user());

        return response()->json([
            'status' => $result['status'],
            'message' => $result['message'],
        ], $result['http']);
    }

    public function verifyLoginCode(VerifyLoginCodeRequest $request): JsonResponse
    {
        $user = $request->user();
        $result = $this->loginVerificationService->verifyCode(
            $user,
            (string) $request->validated('code')
        );

        if (! $result['status']) {
            return response()->json([
                'status' => false,
                'message' => $result['message'],
            ], $result['http']);
        }

        $this->accessTokenService->revokeCurrent($user);

        return $this->completeVerifiedLogin($user, $request, $result['message']);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user) {
            $this->auditLogger->record([
                'actor' => $user,
                'category' => 'auth',
                'action' => 'logout',
                'description' => 'Signed out',
                'subject_type' => $user->getMorphClass(),
                'subject_id' => $user->id,
                'subject_label' => $user->email,
            ], $request);

            $this->notificationService->clearDeviceToken($user);
            $user->currentAccessToken()?->delete();
        }

        return response()->json([
            'status'  => true,
            'message' => 'Logged out successfully.',
        ])
            ->withCookie(AdminGateCookie::forget())
            ->withCookie(AdminRoleCookie::forget());
    }

    public function me(Request $request): JsonResponse
    {
        $response = response()->json([
            'status' => true,
            'data'   => $this->profileService->formatUser($request->user()),
        ]);

        if ($this->accessTokenService->isPending($request->user())) {
            return $response;
        }

        return $this->withAdminGate($response, $request->user());
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

    public function updateOnboarding(UpdateOnboardingRequest $request): JsonResponse
    {
        $user = $this->profileService->updateOnboarding(
            $request->user(),
            $request->validated(),
        );

        return response()->json([
            'status'  => true,
            'message' => 'Onboarding updated.',
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

        $this->passwordService->apply($user, (string) $request->input('password'), false);
        $this->accessTokenService->revokeOthers($user);

        $this->auditLogger->record([
            'actor' => $user,
            'category' => 'auth',
            'action' => 'password_changed',
            'description' => 'Changed account password',
            'subject_type' => $user->getMorphClass(),
            'subject_id' => $user->id,
            'subject_label' => $user->email,
        ], $request);

        return response()->json([
            'status'  => true,
            'message' => 'Password updated successfully.',
        ]);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $result = $this->passwordResetService->sendResetLink(
            (string) $request->validated('email')
        );

        return response()->json([
            'status' => $result['status'],
            'message' => $result['message'],
            'data' => $result['data'] ?? (object) [],
        ], $result['http']);
    }

    public function verifyResetCode(VerifyResetCodeRequest $request): JsonResponse
    {
        $result = $this->passwordResetService->verifyCode(
            (string) $request->validated('email'),
            (string) $request->validated('code')
        );

        return response()->json([
            'status' => $result['status'],
            'message' => $result['message'],
            'data' => $result['data'] ?? (object) [],
        ], $result['http']);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();
        // `confirmed` is validated but not always returned by validated().
        $validated['password_confirmation'] = (string) $request->input('password_confirmation');

        $result = $this->passwordResetService->reset($validated);

        $payload = [
            'status' => $result['status'],
            'message' => $result['message'],
        ];

        if (isset($result['errors'])) {
            $payload['errors'] = $result['errors'];
        }

        return response()->json($payload, $result['http']);
    }

    /**
     * @param  array{name: string, email: string, password: string}  $validated
     */
    private function createAccount(array $validated, string $role): User
    {
        $fullName = trim($validated['name']);
        $nameParts = preg_split('/\s+/', $fullName, 2) ?: [];

        $user = new User();
        $user->forceFill([
            'name' => $fullName,
            'first_name' => $nameParts[0] ?? $fullName,
            'last_name' => $nameParts[1] ?? '',
            'email' => $validated['email'],
            'password' => $validated['password'],
            'password_changed_at' => now(),
            'must_change_password' => false,
            'role' => $role,
            'is_active' => true,
            'accepted_terms_of_use_at' => now(),
            'accepted_privacy_policy_at' => now(),
            'accepted_privacy_notice_at' => now(),
        ])->save();

        return $user;
    }

    private function completeVerifiedLogin(User $user, Request $request, string $message): JsonResponse
    {
        $token = $this->accessTokenService->issueFull($user);
        $user->forceFill(['last_login_at' => now()])->save();

        $this->auditLogger->record([
            'actor' => $user,
            'category' => 'auth',
            'action' => 'login',
            'description' => 'Signed in successfully',
            'subject_type' => $user->getMorphClass(),
            'subject_id' => $user->id,
            'subject_label' => $user->email,
        ], $request);

        return $this->withAdminGate(response()->json([
            'status' => true,
            'message' => $message,
            'data' => [
                'token' => $token,
                'user' => $this->profileService->formatUser($user->fresh() ?? $user),
                'requires_verification' => false,
            ],
        ]), $user);
    }

    private function withAdminGate(JsonResponse $response, ?User $user = null): JsonResponse
    {
        $response = $response->withCookie(AdminGateCookie::make());

        $role = $user?->role;
        if (is_string($role) && array_key_exists($role, AdminRoleSections::ROLE_SECTIONS)) {
            return $response->withCookie(AdminRoleCookie::make($role));
        }

        return $response->withCookie(AdminRoleCookie::forget());
    }
}
