<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Throwable;

class PasswordResetService
{
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
        $resetUrl = $this->buildResetUrl($user->email, $token);

        try {
            $user->notify(new ResetPassword($token));
        } catch (Throwable $e) {
            report($e);

            // When mail is misconfigured, still allow mobile/DEV to continue via returned token.
            if (! $this->shouldExposeResetToken()) {
                return [
                    'status' => false,
                    'message' => 'Unable to send password reset email. Please try again later.',
                    'http' => 500,
                ];
            }
        }

        $data = [];
        if ($this->shouldExposeResetToken()) {
            $data = [
                'email' => $user->email,
                'token' => $token,
                'reset_url' => $resetUrl,
            ];
        }

        return [
            'status' => true,
            'message' => 'Password reset instructions have been sent to your email.',
            'data' => $data,
            'http' => 200,
        ];
    }

    /**
     * @param  array{email: string, token: string, password: string, password_confirmation: string}  $payload
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

    /**
     * Expose token/URL when real SMTP is not configured (log/array mailers, or debug).
     */
    public function shouldExposeResetToken(): bool
    {
        $mailer = (string) config('mail.default');

        return in_array($mailer, ['log', 'array'], true) || (bool) config('app.debug');
    }
}
