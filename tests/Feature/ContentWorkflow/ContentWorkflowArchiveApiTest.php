<?php

namespace Tests\Feature\ContentWorkflow;

use App\Models\AdminNotification;
use App\Models\BlockedWord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContentWorkflowArchiveApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_can_archive_published_content(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $publisher = User::factory()->create(['role' => 'publisher']);
        $word = $this->publishedWord('archiveword');

        Sanctum::actingAs($editor);

        $this->putJson("/api/v1/admin/blocked-words/{$word->id}/archive")
            ->assertOk()
            ->assertJsonPath('data.workflow_status', 'archived');

        $this->assertDatabaseHas('blocked_words', [
            'id' => $word->id,
            'workflow_status' => 'archived',
        ]);

        $this->assertTrue(
            AdminNotification::query()
                ->where('user_id', $publisher->id)
                ->where('type', 'content_archived')
                ->exists()
        );
    }

    public function test_editor_restore_submits_for_publisher_review(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $publisher = User::factory()->create(['role' => 'publisher']);
        $word = $this->publishedWord('restoreword');
        $word->forceFill(['workflow_status' => 'archived'])->save();

        Sanctum::actingAs($editor);

        $this->putJson("/api/v1/admin/blocked-words/{$word->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.workflow_status', 'pending_review')
            ->assertJsonPath(
                'message',
                'Restore submitted for publisher review. It stays hidden until they publish it.',
            );

        $this->assertTrue(
            AdminNotification::query()
                ->where('user_id', $publisher->id)
                ->where('type', 'content_restore_requested')
                ->exists()
        );
    }

    public function test_editor_cannot_archive_a_draft(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $word = BlockedWord::query()->create([
            'word' => 'draftword',
            'is_active' => true,
            'workflow_status' => 'draft',
        ]);

        Sanctum::actingAs($editor);

        $this->putJson("/api/v1/admin/blocked-words/{$word->id}/archive")
            ->assertUnprocessable();
    }

    public function test_publisher_cannot_archive_content(): void
    {
        $publisher = User::factory()->create(['role' => 'publisher']);
        $word = $this->publishedWord('publisherarchive');

        Sanctum::actingAs($publisher);

        $this->putJson("/api/v1/admin/blocked-words/{$word->id}/archive")
            ->assertForbidden();
    }

    public function test_super_admin_restore_publishes_immediately(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $word = $this->publishedWord('adminrestore');
        $word->forceFill(['workflow_status' => 'archived'])->save();

        Sanctum::actingAs($admin);

        $this->putJson("/api/v1/admin/blocked-words/{$word->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.workflow_status', 'published')
            ->assertJsonPath('message', 'Restored and published.');
    }

    private function publishedWord(string $word): BlockedWord
    {
        return BlockedWord::query()->create([
            'word' => $word,
            'is_active' => true,
            'workflow_status' => 'published',
        ]);
    }
}
