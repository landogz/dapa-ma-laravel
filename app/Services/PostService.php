<?php

namespace App\Services;

use App\Models\Post;
use App\Models\User;
use App\Repositories\PostRepository;
use App\Services\AdminNotificationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class PostService
{
    private PostRepository $postRepository;
    private AdminNotificationService $adminNotificationService;

    public function __construct(PostRepository $postRepository, AdminNotificationService $adminNotificationService)
    {
        $this->postRepository = $postRepository;
        $this->adminNotificationService = $adminNotificationService;
    }

    public function listAdmin(int $perPage = 20): LengthAwarePaginator
    {
        return $this->postRepository->paginateAdmin($perPage);
    }

    public function listPublished(int $perPage = 20, ?string $categorySlug = null): LengthAwarePaginator
    {
        return $this->postRepository->paginatePublished($perPage, $categorySlug);
    }

    public function listForSelection(): Collection
    {
        return $this->postRepository->selectionList();
    }

    public function find(int $id): Post
    {
        return $this->postRepository->findOrFail($id);
    }

    public function createDraft(array $data, User $author): Post
    {
        $mediaUrl = $this->storeMediaFile($data['media_file'] ?? null);

        return $this->postRepository->create([
            'title'       => $data['title'],
            'body'        => $data['body'],
            'category_id' => $data['category_id'],
            'media_url'   => $mediaUrl,
            'youtube_url' => $data['youtube_url'] ?? null,
            'status'      => 'draft',
            'author_id'   => $author->id,
        ]);
    }

    public function updateDraft(Post $post, array $data): Post
    {
        $this->assertStatus($post, ['draft', 'pending_review']);

        $payload = array_filter($data, fn ($value) => $value !== null);

        if (($data['media_file'] ?? null) instanceof UploadedFile) {
            $this->deleteStoredMedia($post->media_url);
            $payload['media_url'] = $this->storeMediaFile($data['media_file']);
        }

        unset($payload['media_file']);

        return $this->postRepository->update($post, $payload);
    }

    public function submitForReview(Post $post, User $editor): Post
    {
        $this->assertStatus($post, ['draft']);

        $post = $this->postRepository->update($post, ['status' => 'pending_review', 'review_notes' => null]);

        $this->adminNotificationService->notifyPostSubmitted($post, $editor);

        return $post;
    }

    public function reject(Post $post, string $reviewNotes, User $publisher): Post
    {
        $this->assertStatus($post, ['pending_review']);

        $post = $this->postRepository->update($post, [
            'status'       => 'draft',
            'review_notes' => $reviewNotes,
        ]);

        $this->adminNotificationService->notifyPostRejected($post, $publisher, $reviewNotes);

        return $post;
    }

    public function schedule(Post $post, string $publishDate, User $publisher): Post
    {
        $this->assertStatus($post, ['pending_review']);

        $post = $this->postRepository->update($post, [
            'status'       => 'scheduled',
            'publish_date' => Carbon::parse($publishDate),
            'review_notes' => null,
        ]);

        $this->adminNotificationService->notifyPostScheduled($post, $publisher);

        return $post;
    }

    public function publishNow(Post $post, User $publisher): Post
    {
        $this->assertStatus($post, ['pending_review', 'scheduled']);

        $post = $this->postRepository->update($post, [
            'status'       => 'published',
            'publish_date' => Carbon::now(),
            'review_notes' => null,
        ]);

        // Reuse the scheduled notification so the author is informed that the post is live.
        $this->adminNotificationService->notifyPostScheduled($post, $publisher);

        return $post;
    }

    public function archive(Post $post): Post
    {
        $this->assertStatus($post, ['published', 'scheduled']);

        return $this->postRepository->update($post, ['status' => 'archived']);
    }

    public function delete(Post $post): void
    {
        $post->delete();
    }

    public function publishScheduled(): int
    {
        return $this->postRepository->publishDue();
    }

    private function assertStatus(Post $post, array $allowedStatuses): void
    {
        if (! in_array($post->status, $allowedStatuses, true)) {
            abort(422, "Cannot perform this action on a post with status '{$post->status}'.");
        }
    }

    private function storeMediaFile(?UploadedFile $mediaFile): ?string
    {
        if (! $mediaFile) {
            return null;
        }

        $path = $mediaFile->store('posts/media', 'public');

        return Storage::disk('public')->url($path);
    }

    private function deleteStoredMedia(?string $mediaUrl): void
    {
        if (! $mediaUrl) {
            return;
        }

        $storagePrefix = Storage::disk('public')->url('/');

        if (! str_starts_with($mediaUrl, $storagePrefix)) {
            return;
        }

        $relativePath = ltrim(substr($mediaUrl, strlen($storagePrefix)), '/');

        if ($relativePath !== '') {
            Storage::disk('public')->delete($relativePath);
        }
    }
}
