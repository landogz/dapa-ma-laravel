<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\PasswordResetCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['password.uncompromised' => false]);
    }

    public function test_unknown_email_does_not_reveal_accounts(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'nobody@example.com',
        ])->assertOk()
            ->assertJsonPath('status', true);
    }

    public function test_reset_code_is_consumed_and_tokens_are_revoked(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $existing = $user->createToken('api-token', ['*'])->plainTextToken;

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => $user->email,
        ])->assertOk();

        $code = '';
        Notification::assertSentTo($user, PasswordResetCodeNotification::class, function (PasswordResetCodeNotification $notification) use (&$code): bool {
            $code = $notification->code;

            return true;
        });

        $verify = $this->postJson('/api/v1/auth/verify-reset-code', [
            'email' => $user->email,
            'code' => $code,
        ])->assertOk();

        $ticket = $verify->json('data.token');
        $this->assertNotEmpty($ticket);
        $this->assertNotSame($code, $ticket);

        $this->postJson('/api/v1/auth/verify-reset-code', [
            'email' => $user->email,
            'code' => $code,
        ])->assertUnprocessable();

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => $ticket,
            'password' => 'FreshPass!9',
            'password_confirmation' => 'FreshPass!9',
        ])->assertOk();

        $this->withToken($existing)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }
}
