<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\PasswordResetCodeNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class PasswordResetService
{
    private const OTP_TTL_SECONDS = 3600;

    /**
     * @return array{status: bool, message: string, data?: array<string, mixed>, http: int}
     */
    public function sendResetLink(string $email): array
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            return [
                'status' => false,
                'message' => __("We can't find a user with that email address."),
                'http' => 422,
            ];
        }

        $token = Password::broker()->createToken($user);
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $resetUrl = $this->buildResetUrl($user->email, $token);

        Cache::put($this->otpCacheKey($user->email), [
            'code_hash' => Hash::make($code),
            'token' => $token,
        ], self::OTP_TTL_SECONDS);

        $user->notify(new PasswordResetCodeNotification($code, $resetUrl));

        return [
            'status' => true,
            'message' => 'If that email is registered, we sent a 6-digit code. Check your inbox before resetting your password.',
            'data' => [
                'email' => $user->email,
            ],
            'http' => 200,
        ];
    }

    /**
     * @return array{status: bool, message: string, data?: array<string, mixed>, http: int}
     */
    public function verifyCode(string $email, string $code): array
    {
        $payload = Cache::get($this->otpCacheKey($email));
        if (! is_array($payload) || empty($payload['code_hash']) || empty($payload['token'])) {
            return [
                'status' => false,
                'message' => 'Invalid or expired reset code. Request a new one.',
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

        return [
            'status' => true,
            'message' => 'Code verified. You can set a new password.',
            'data' => [
                'email' => $email,
                'token' => $payload['token'],
            ],
            'http' => 200,
        ];
    }

    /**
     * @param  array{email: string, token: string, password: string, password_confirmation?: string}  $payload
     * @return array{status: bool, message: string, errors?: array<string, array<int, string>>, http: int}
     */
    public function reset(array $payload): array
    {
        $status = Password::broker()->reset(
            [
                'email' => $payload['email'],
                'token' => $payload['token'],
                'password' => $payload['password'],
                'password_confirmation' => $payload['password_confirmation']
                    ?? $payload['password'],
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();
                Cache::forget($this->otpCacheKey($user->email));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return [
                'status' => true,
                'message' => 'Password has been reset successfully. You can sign in now.',
                'http' => 200,
            ];
        }

        return [
            'status' => false,
            'message' => __($status),
            'errors' => [
                'email' => [__($status)],
            ],
            'http' => 422,
        ];
    }

    public function buildResetUrl(string $email, string $token): string
    {
        return url('/reset-password?'.http_build_query([
            'token' => $token,
            'email' => $email,
        ]));
    }

    private function otpCacheKey(string $email): string
    {
        return 'password_reset_otp:'.strtolower(trim($email));
    }
}
