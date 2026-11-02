<?php

namespace Tests\Feature;

use App\Models\BlockedWord;
use App\Models\User;
use App\Services\BlockedWord\BlockedWordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BlockedWordApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_catalog_returns_active_words_only(): void
    {
        BlockedWord::query()->create(['word' => 'foobar', 'is_active' => true]);
        BlockedWord::query()->create(['word' => 'hiddenword', 'is_active' => false]);

        $this->getJson('/api/v1/blocked-words')
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.words', ['foobar'])
            ->assertJsonMissing(['hiddenword']);
    }

    public function test_mask_uses_word_boundaries_and_ignores_inactive(): void
    {
        BlockedWord::query()->create(['word' => 'ass', 'is_active' => true]);
        BlockedWord::query()->create(['word' => 'damn', 'is_active' => false]);

        $service = app(BlockedWordService::class);

        $this->assertSame('That class is fine', $service->mask('That class is fine'));
        $this->assertSame('What an ****.', $service->mask('What an ASS.'));
        $this->assertSame('This is damn loud', $service->mask('This is damn loud'));
        $this->assertSame('<p>What an ****.</p>', $service->maskHtml('<p>What an ASS.</p>'));
    }

    public function test_admin_can_create_update_and_delete_word(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/blocked-words', [
            'word' => 'BadWord',
            'is_active' => true,
        ])->assertCreated()
            ->assertJsonPath('data.word', 'badword');

        $id = BlockedWord::query()->where('word', 'badword')->value('id');

        $this->putJson("/api/v1/admin/blocked-words/{$id}", [
            'word' => 'badword',
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->getJson('/api/v1/admin/blocked-words?is_active=0')
            ->assertOk()
            ->assertJsonPath('data.data.0.word', 'badword');

        $this->getJson('/api/v1/admin/blocked-words?is_active=true')
            ->assertOk()
            ->assertJsonPath('data.data', []);

        $this->deleteJson("/api/v1/admin/blocked-words/{$id}")
            ->assertOk();

        $this->assertDatabaseMissing('blocked_words', ['id' => $id]);
    }

    public function test_guest_cannot_manage_blocked_words(): void
    {
        $this->postJson('/api/v1/admin/blocked-words', [
            'word' => 'nope',
        ])->assertUnauthorized();
    }

    public function test_publisher_status_counts_only_include_pending_review(): void
    {
        BlockedWord::query()->create([
            'word' => 'publishedword',
            'is_active' => true,
            'workflow_status' => 'published',
        ]);
        BlockedWord::query()->create([
            'word' => 'pendingword',
            'is_active' => true,
            'workflow_status' => 'pending_review',
        ]);

        $publisher = User::factory()->create(['role' => 'publisher']);
        Sanctum::actingAs($publisher);

        $counts = $this->getJson('/api/v1/admin/blocked-words')
            ->assertOk()
            ->json('meta.status_counts');

        $this->assertSame(1, (int) ($counts[''] ?? 0));
        $this->assertSame(1, (int) ($counts['1'] ?? 0));
        $this->assertSame(0, (int) ($counts['0'] ?? 0));
    }
}
