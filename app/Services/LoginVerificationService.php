<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\LoginCodeNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Throwable;

class LoginVerificationService
{
    private const OTP_TTL_SECONDS = 600;

    /**
     * @return array{status: bool, message: string, http: int}
     */
    public function sendCode(User $user): array
    {
        $email = strtolower(trim((string) $user->email));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || str_ends_with($email, '.local')) {
            return [
                'status' => false,
                'message' => 'This account email cannot receive mail. Ask an admin to set a real email address first.',
                'http' => 422,
            ];
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put($this->cacheKey($user), [
            'code_hash' => Hash::make($code),
            'verified' => false,
        ], self::OTP_TTL_SECONDS);

        try {
            $user->notify(new LoginCodeNotification($code));
        } catch (Throwable $e) {
            Cache::forget($this->cacheKey($user));
            Log::error('Login verification email failed.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => false,
                'message' => 'We could not send the verification code to your email. Please try again later or use a deliverable email address.',
                'http' => 503,
            ];
        }

        return [
            'status' => true,
            'message' => 'We sent a 6-digit code to your email. Enter it to finish signing in.',
            'http' => 200,
        ];
    }

    /**
     * @return array{status: bool, message: string, http: int}
     */
    public function verifyCode(User $user, string $code): array
    {
        $payload = Cache::get($this->cacheKey($user));
        if (! is_array($payload) || empty($payload['code_hash'])) {
            return [
                'status' => false,
                'message' => 'Invalid or expired verification code. Request a new one.',
                'http' => 422,
            ];
        }

        if (! Hash::check($code, (string) $payload['code_hash'])) {
            return [
                'status' => false,
                'message' => 'That code does not match. Check your email and try again.',
                'http' => 422,
            ];
        }

        Cache::put($this->cacheKey($user), [
            'code_hash' => $payload['code_hash'],
            'verified' => true,
        ], self::OTP_TTL_SECONDS);

        return [
            'status' => true,
            'message' => 'Sign-in verified.',
            'http' => 200,
        ];
    }

    private function cacheKey(User $user): string
    {
        return 'login_otp:'.$user->id;
    }
}
