<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_save_and_clear_about_me(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/auth/profile', [
            'about' => '  Tell others a little about yourself from tests.  ',
        ])
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.about', 'Tell others a little about yourself from tests.');

        $this->assertSame(
            'Tell others a little about yourself from tests.',
            $user->fresh()->about,
        );

        $this->putJson('/api/v1/auth/profile', [
            'about' => '',
        ])
            ->assertOk()
            ->assertJsonPath('data.about', null);

        $this->assertNull($user->fresh()->about);
    }

    public function test_about_me_cannot_exceed_70_characters(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/auth/profile', [
            'about' => str_repeat('a', 71),
        ])->assertUnprocessable();
    }

    public function test_custom_journal_feelings_are_private_to_each_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/auth/profile', [
            'journal_custom_feelings' => ['Drained', 'Grounded', 'Hopeful'],
        ])
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.journal_custom_feelings', ['Drained']);

        $this->assertSame(['Drained'], $user->fresh()->journal_custom_feelings);

        Sanctum::actingAs($other);
        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.journal_custom_feelings', []);
    }
}
