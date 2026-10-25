<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DiaryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_save_multiple_entries_on_the_same_date(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/diary-entries', [
            'entry_date' => '2026-10-24',
            'body_html' => 'Morning reflection',
            'sky' => 'clear_skies',
        ])->assertCreated();

        $this->postJson('/api/v1/diary-entries', [
            'entry_date' => '2026-10-24',
            'body_html' => 'Evening reflection',
            'sky' => 'overcast',
        ])->assertCreated();

        $this->assertSame(2, $user->fresh()->diaryEntries()->count());

        $list = $this->getJson('/api/v1/diary-entries')->assertOk();
        $this->assertCount(2, $list->json('data.data'));
    }

    public function test_user_can_delete_own_entry(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $created = $this->postJson('/api/v1/diary-entries', [
            'entry_date' => '2026-10-24',
            'body_html' => 'To delete',
        ])->assertCreated();

        $id = $created->json('data.id');

        $this->deleteJson("/api/v1/diary-entries/{$id}")
            ->assertOk()
            ->assertJsonPath('status', true);

        $this->assertSame(0, $user->fresh()->diaryEntries()->count());
    }

    public function test_user_can_filter_entries_by_date_sky_and_feeling(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/diary-entries', [
            'entry_date' => '2026-10-24',
            'sky' => 'clear_skies',
            'feelings' => ['Hopeful'],
            'body_html' => 'Today',
        ])->assertCreated();

        $this->postJson('/api/v1/diary-entries', [
            'entry_date' => '2026-09-01',
            'sky' => 'stormy',
            'feelings' => ['Lonely', 'test'],
            'body_html' => 'Last month',
        ])->assertCreated();

        $byDate = $this->getJson('/api/v1/diary-entries?from=2026-10-24&to=2026-10-24')
            ->assertOk();
        $this->assertCount(1, $byDate->json('data.data'));
        $this->assertSame('Today', $byDate->json('data.data.0.body_html'));

        $bySky = $this->getJson('/api/v1/diary-entries?sky=stormy')->assertOk();
        $this->assertCount(1, $bySky->json('data.data'));

        $byFeeling = $this->getJson('/api/v1/diary-entries?feeling=test')->assertOk();
        $this->assertCount(1, $byFeeling->json('data.data'));
        $this->assertSame('Last month', $byFeeling->json('data.data.0.body_html'));
    }

    public function test_saving_a_custom_feeling_keeps_it_on_the_user_profile(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/diary-entries', [
            'entry_date' => '2026-10-24',
            'body_html' => 'With a custom feeling',
            'feelings' => ['Grateful', 'Tired of waiting'],
        ])->assertCreated();

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.journal_custom_feelings', ['Tired of waiting']);
    }
}
