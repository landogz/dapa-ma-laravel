<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterBootstrapApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['password.uncompromised' => false]);
    }

    public function test_public_register_always_creates_app_user(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload('first@example.com'))
            ->assertCreated()
            ->assertJsonPath('data.user.role', 'app_user');

        $this->assertSame('app_user', User::query()->first()?->role);
    }

    public function test_bootstrap_admin_creates_the_first_super_admin_only(): void
    {
        $this->postJson('/api/v1/auth/bootstrap-admin', $this->payload('admin@example.com'))
            ->assertCreated()
            ->assertJsonPath('data.user.role', 'super_admin');

        $this->postJson('/api/v1/auth/bootstrap-admin', $this->payload('second@example.com'))
            ->assertForbidden();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $email): array
    {
        return [
            'name' => 'Test User',
            'email' => $email,
            'password' => 'FreshPass!9',
            'password_confirmation' => 'FreshPass!9',
            'accepted_terms_of_use' => true,
            'accepted_privacy_policy' => true,
            'accepted_privacy_notice' => true,
        ];
    }
}
