<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckEmailApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_email_is_rejected_before_register(): void
    {
        $this->getJson('/api/v1/auth/check-email?email=not-an-email')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_available_email_can_proceed(): void
    {
        $this->getJson('/api/v1/auth/check-email?email=new.user@example.com')
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.available', true);
    }

    public function test_taken_email_cannot_proceed(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->getJson('/api/v1/auth/check-email?email=Taken@example.com')
            ->assertOk()
            ->assertJsonPath('data.available', false);
    }
}
