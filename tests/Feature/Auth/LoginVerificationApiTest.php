<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\LoginCodeNotification;
use App\Support\Auth\AdminGateCookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LoginVerificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_login_skips_email_verification(): void
    {
        Notification::fake();
        $this->app['env'] = 'local';
        $user = User::factory()->create();

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('data.requires_verification', false)
            ->assertPlainCookie(AdminGateCookie::NAME, '1');

        Notification::assertNothingSent();

        $this->withToken($login->json('data.token'))
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->assertPlainCookie(AdminGateCookie::NAME, '1');
    }

    public function test_login_issues_pending_token_and_blocks_other_routes(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('data.requires_verification', true)
            ->assertCookieMissing(AdminGateCookie::NAME);

        $token = $login->json('data.token');
        $this->assertNotEmpty($token);

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertForbidden()
            ->assertJsonPath('data.requires_verification', true);
    }

    public function test_verify_consumes_otp_and_issues_full_token(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $pending = $login->json('data.token');
        $code = '';
        Notification::assertSentTo($user, LoginCodeNotification::class, function (LoginCodeNotification $notification) use (&$code): bool {
            $code = $notification->code;

            return true;
        });

        $verify = $this->withToken($pending)
            ->postJson('/api/v1/auth/verify-login-code', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('data.requires_verification', false);

        $fullToken = $verify->json('data.token');
        $this->assertNotEmpty($fullToken);
        $this->assertNotSame($pending, $fullToken);

        $this->app['auth']->forgetGuards();

        $this->withToken($fullToken)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);

        $this->app['auth']->forgetGuards();

        $this->withToken($pending)
            ->postJson('/api/v1/auth/verify-login-code', ['code' => $code])
            ->assertUnauthorized();
    }

    public function test_wrong_code_does_not_unlock_the_account(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $this->withToken($login->json('data.token'))
            ->postJson('/api/v1/auth/verify-login-code', ['code' => '000000'])
            ->assertUnprocessable();

        $this->withToken($login->json('data.token'))
            ->getJson('/api/v1/auth/me')
            ->assertForbidden();
    }

    public function test_admin_pages_require_the_gate_cookie(): void
    {
        $this->get('/admin/dashboard')->assertRedirect('/admin/login');

        $this->withAdminGate()
            ->get('/admin/dashboard')
            ->assertOk();
    }

    public function test_health_does_not_expose_environment(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonMissingPath('data.environment');
    }

    public function test_app_user_cannot_list_users(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'app_user']));

        $this->getJson('/api/v1/users')->assertForbidden();
    }

    public function test_super_admin_user_list_hides_fcm_token(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        User::factory()->create([
            'fcm_token' => 'secret-device-token',
            'fcm_platform' => 'ios',
        ]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/users')
            ->assertOk()
            ->assertJsonMissingPath('data.data.0.fcm_token')
            ->assertJsonMissingPath('data.data.1.fcm_token');
    }
}
