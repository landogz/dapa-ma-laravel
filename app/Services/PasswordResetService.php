<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\PasswordResetCodeNotification;
use App\Services\Password\PasswordService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Throwable;

class PasswordResetService
{
    private const OTP_TTL_SECONDS = 3600;
    private const TICKET_TTL_SECONDS = 900;
    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly PasswordService $passwordService,
    ) {
    }

    /**
     * @return array{status: bool, message: string, data?: array<string, mixed>, http: int}
     */
    public function sendResetLink(string $email): array
    {
        $generic = [
            'status' => true,
            'message' => 'If that email is registered, we sent a 6-digit code. Check your inbox before resetting your password.',
            'http' => 200,
        ];

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            return $generic;
        }

        $token = Password::broker()->createToken($user);
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $resetUrl = $this->buildResetUrl($user->email, $token);

        Cache::put($this->otpCacheKey($user->email), [
            'code_hash' => Hash::make($code),
            'token' => $token,
            'attempts' => 0,
        ], self::OTP_TTL_SECONDS);

        try {
            $user->notify(new PasswordResetCodeNotification($code, $resetUrl));
        } catch (Throwable $e) {
            Cache::forget($this->otpCacheKey($user->email));
            Log::error('Password reset email failed.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);

            return $generic;
        }

        return $generic;
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

        $attempts = (int) ($payload['attempts'] ?? 0);
        if ($attempts >= self::MAX_ATTEMPTS) {
            Cache::forget($this->otpCacheKey($email));

            return [
                'status' => false,
                'message' => 'Too many incorrect codes. Request a new one.',
                'http' => 422,
            ];
        }

        if (! Hash::check($code, (string) $payload['code_hash'])) {
            $payload['attempts'] = $attempts + 1;
            Cache::put($this->otpCacheKey($email), $payload, self::OTP_TTL_SECONDS);

            return [
                'status' => false,
                'message' => 'That code does not match. Check your email and try again.',
                'http' => 422,
            ];
        }

        $ticket = Str::random(64);
        Cache::put($this->ticketCacheKey($ticket), [
            'email' => strtolower(trim($email)),
            'token' => $payload['token'],
        ], self::TICKET_TTL_SECONDS);
        Cache::forget($this->otpCacheKey($email));

        return [
            'status' => true,
            'message' => 'Code verified. You can set a new password.',
            'data' => [
                'email' => $email,
                'token' => $ticket,
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
        $ticket = Cache::get($this->ticketCacheKey((string) $payload['token']));
        $brokerToken = is_array($ticket) ? (string) ($ticket['token'] ?? '') : (string) $payload['token'];
        $email = strtolower(trim((string) $payload['email']));

        if (is_array($ticket) && isset($ticket['email']) && $ticket['email'] !== $email) {
            return [
                'status' => false,
                'message' => 'This reset link is invalid or has expired.',
                'errors' => [
                    'email' => ['This reset link is invalid or has expired.'],
                ],
                'http' => 422,
            ];
        }

        $status = Password::broker()->reset(
            [
                'email' => $payload['email'],
                'token' => $brokerToken,
                'password' => $payload['password'],
                'password_confirmation' => $payload['password_confirmation']
                    ?? $payload['password'],
            ],
            function (User $user, string $password) use ($payload): void {
                $this->passwordService->apply($user, $password, false);
                $user->tokens()->delete();
                Cache::forget($this->otpCacheKey($user->email));
                Cache::forget($this->ticketCacheKey((string) $payload['token']));
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

    private function ticketCacheKey(string $ticket): string
    {
        return 'password_reset_ticket:'.$ticket;
    }
}
