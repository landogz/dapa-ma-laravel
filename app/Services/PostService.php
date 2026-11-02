<?php

namespace App\Services;

use App\Models\Post;
use App\Models\User;
use App\Repositories\PostRepository;
use App\Services\AdminNotificationService;
use App\Services\BlockedWord\BlockedWordService;
use App\Support\Html\SafeHtml;
use Illuminate\Http\UploadedFile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class PostService
{
    private PostRepository $postRepository;
    private AdminNotificationService $adminNotificationService;
    private UserNotificationService $userNotificationService;
    private BlockedWordService $blockedWordService;

    public function __construct(
        PostRepository $postRepository,
        AdminNotificationService $adminNotificationService,
        UserNotificationService $userNotificationService,
        BlockedWordService $blockedWordService,
    ) {
        $this->postRepository = $postRepository;
        $this->adminNotificationService = $adminNotificationService;
        $this->userNotificationService = $userNotificationService;
        $this->blockedWordService = $blockedWordService;
    }

    public function listAdmin(int $perPage = 20, ?User $actor = null): LengthAwarePaginator
    {
        return $this->postRepository->paginateAdmin($perPage, $actor);
    }

    public function listPublished(
        int $perPage = 20,
        ?string $categorySlug = null,
        ?bool $featuredOnly = null,
    ): LengthAwarePaginator {
        return $this->postRepository->paginatePublished($perPage, $categorySlug, $featuredOnly);
    }

    public function listForSelection(): Collection
    {
        return $this->postRepository->selectionList();
    }

    public function find(int $id): Post
    {
        return $this->postRepository->findOrFail($id);
    }

    public function findPublic(string $key): Post
    {
        return $this->postRepository->findPublic($key);
    }

    public function findAdminDetail(int $id, ?User $actor = null): Post
    {
        $post = $this->postRepository->findAdminDetail($id);

        if ($actor?->role === 'publisher' && ! $this->publisherCanView($post)) {
            $returnedForRevision = $post->status === 'draft'
                || ($post->status === 'published' && ($post->revision_status ?? null) === 'draft');
            $alreadyResolved = $post->status === 'published' && ! $post->hasPendingRevision();

            abort(response()->json([
                'status' => false,
                'code' => $alreadyResolved ? 'review_resolved' : ($returnedForRevision ? 'review_returned' : 'not_in_queue'),
                'message' => $alreadyResolved
                    ? 'This post update was already published. It is no longer in your review queue.'
                    : ($returnedForRevision
                        ? 'This post was returned for revision. It will come back to your queue when the editor resubmits it.'
                        : 'This post is not available in your publisher review queue.'),
            ], ($alreadyResolved || $returnedForRevision) ? 409 : 404));
        }

        return $post;
    }

    public function createDraft(array $data, User $author): Post
    {
        $data = $this->applyWordBlocking($data);
        $mediaUrl = $this->storeMediaFile($data['media_file'] ?? null);
        $publishMeta = $this->resolveSuperAdminPublishMeta($author);

        $post = $this->postRepository->create([
            'title'             => $data['title'],
            'body'              => $data['body'],
            'category_id'       => $data['category_id'],
            'media_url'         => $mediaUrl,
            'youtube_url'       => $data['youtube_url'] ?? null,
            'comments_enabled'  => array_key_exists('comments_enabled', $data)
                ? (bool) $data['comments_enabled']
                : true,
            'is_featured'       => array_key_exists('is_featured', $data)
                ? (bool) $data['is_featured']
                : false,
            'status'            => $publishMeta['status'],
            'publish_date'      => $publishMeta['publish_date'],
            'author_id'         => $author->id,
        ]);

        if ($post->status === 'published') {
            $this->userNotificationService->notifyNewPost($post);
        }

        return $post;
    }

    public function updateDraft(Post $post, array $data, ?User $actor = null): Post
    {
        $data = $this->applyWordBlocking($data);

        if ($actor?->role === 'editor' && $post->status === 'published') {
            return $this->savePendingRevision($post, $data, $actor);
        }

        $allowedStatuses = $actor?->role === 'super_admin'
            ? ['draft', 'pending_review', 'published', 'scheduled', 'archived']
            : ['draft', 'pending_review'];

        $this->assertStatus($post, $allowedStatuses);
        $wasPublished = $post->status === 'published';

        // Keep explicit nulls for clearable nullable columns (e.g. removing YouTube URL).
        $clearableNulls = ['youtube_url'];
        $payload = [];
        foreach ($data as $key => $value) {
            if ($value !== null || in_array($key, $clearableNulls, true)) {
                $payload[$key] = $value;
            }
        }

        if (($data['media_file'] ?? null) instanceof UploadedFile) {
            $this->deleteStoredMedia($post->media_url);
            $payload['media_url'] = $this->storeMediaFile($data['media_file']);
        }

        unset($payload['media_file']);

        if ($actor?->role !== 'super_admin') {
            unset($payload['status'], $payload['publish_date']);
        } elseif ($this->payloadTouchesPostContent($payload)) {
            $payload = $this->applySuperAdminPublishDefaults($payload, $post);
        }

        if (($payload['status'] ?? $post->status) === 'scheduled' && ! empty($data['publish_date'])) {
            $payload['publish_date'] = Carbon::parse($data['publish_date']);
        }

        if (array_key_exists('comments_enabled', $payload)) {
            $payload['comments_enabled'] = (bool) $payload['comments_enabled'];
        }

        if (array_key_exists('is_featured', $payload)) {
            $payload['is_featured'] = (bool) $payload['is_featured'];
        }

        $post = $this->postRepository->update($post, $payload);

        if (! $wasPublished && $post->status === 'published') {
            $this->userNotificationService->notifyNewPost($post);
        }

        return $post;
    }

    public function submitForReview(Post $post, User $editor): Post
    {
        // Super admins never wait for publisher approval — publish immediately.
        if ($editor->role === 'super_admin') {
            return $this->publishNow($post, $editor);
        }

        if ($post->status === 'published') {
            if (! $post->hasPendingRevision() || $post->revision_status !== 'draft') {
                abort(422, 'Save a draft update before submitting it for publisher review.');
            }

            $post = $this->postRepository->update($post, [
                'revision_status' => 'pending_review',
                'review_notes' => null,
            ]);

            $this->adminNotificationService->notifyPostSubmitted($post, $editor);

            return $post;
        }

        $this->assertStatus($post, ['draft']);

        $post = $this->postRepository->update($post, ['status' => 'pending_review', 'review_notes' => null]);

        $this->adminNotificationService->notifyPostSubmitted($post, $editor);

        return $post;
    }

    public function reject(Post $post, string $reviewNotes, User $publisher): Post
    {
        if ($post->status === 'published' && $post->hasPendingRevision()) {
            if ($publisher->role === 'publisher' && $post->revision_status !== 'pending_review') {
                abort(403, 'This update is already with the editor for revision.');
            }

            $post = $this->postRepository->update($post, [
                'revision_status' => 'draft',
                'review_notes' => $reviewNotes,
            ]);

            $this->adminNotificationService->notifyPostRejected($post, $publisher, $reviewNotes);

            return $post;
        }

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
        if ($post->isRevisionAwaitingPublisher()) {
            abort(422, 'Published updates cannot be scheduled. Approve the update to apply it now.');
        }

        $this->assertStatus($post, ['pending_review', 'scheduled']);
        $wasScheduled = $post->status === 'scheduled';

        $post = $this->postRepository->update($post, [
            'status'       => 'scheduled',
            'publish_date' => Carbon::parse($publishDate),
            'review_notes' => null,
        ]);

        $this->adminNotificationService->notifyPostScheduled($post, $publisher, $wasScheduled);

        return $post;
    }

    public function publishNow(Post $post, User $publisher): Post
    {
        if ($post->status === 'published' && $post->hasPendingRevision()) {
            if ($publisher->role === 'publisher' && $post->revision_status !== 'pending_review') {
                abort(403, 'This update is with the editor. It returns to your queue when they resubmit it.');
            }

            return $this->applyPendingRevision($post, $publisher);
        }

        $allowed = ['pending_review', 'scheduled'];
        if ($publisher->role === 'super_admin') {
            $allowed[] = 'draft';
        }

        $this->assertStatus($post, $allowed);

        $post = $this->postRepository->update($post, [
            'status'       => 'published',
            'publish_date' => Carbon::now(),
            'review_notes' => null,
            'pending_revision' => null,
            'revision_status' => null,
        ]);

        // Reuse the scheduled notification so the author is informed that the post is live.
        $this->adminNotificationService->notifyPostScheduled($post, $publisher);
        $this->userNotificationService->notifyNewPost($post);

        return $post;
    }

    public function archive(Post $post, User $actor): Post
    {
        if (! in_array($actor->role, ['editor', 'super_admin'], true)) {
            abort(403, 'Only editors and Super Admin can archive posts.');
        }

        $this->assertStatus($post, ['published', 'scheduled']);

        $post = $this->postRepository->update($post, [
            'status' => 'archived',
            'pending_revision' => null,
            'revision_status' => null,
            'review_notes' => null,
        ]);

        if ($actor->role === 'editor') {
            $this->adminNotificationService->notifyPostArchived($post, $actor);
        }

        return $post;
    }

    public function restore(Post $post, User $actor): Post
    {
        $this->assertStatus($post, ['archived']);

        if (! in_array($actor->role, ['editor', 'super_admin'], true)) {
            abort(403, 'Only editors and Super Admin can restore archived posts.');
        }

        if ($actor->role === 'super_admin') {
            return $this->postRepository->update($post, [
                'status' => 'published',
                'publish_date' => $post->publish_date ?? Carbon::now(),
                'review_notes' => null,
                'pending_revision' => null,
                'revision_status' => null,
            ]);
        }

        $post = $this->postRepository->update($post, [
            'status' => 'pending_review',
            'review_notes' => null,
            'pending_revision' => null,
            'revision_status' => null,
        ]);

        $this->adminNotificationService->notifyPostRestoreRequested($post, $actor);

        return $post;
    }

    public function delete(Post $post): void
    {
        $this->deleteStoredMedia($post->getRawOriginal('media_url') ?? $post->media_url);
        $post->delete();
    }

    /**
     * Editors and Super Admin may delete draft posts, or discard a staged update on a live post.
     *
     * @return array{mode: string, post: ?Post}
     */
    public function deleteDraft(Post $post, User $actor): array
    {
        if (! in_array($actor->role, ['editor', 'super_admin'], true)) {
            abort(403, 'Only editors and Super Admin can delete drafts.');
        }

        if ($post->status === 'published' && ($post->revision_status ?? null) === 'draft') {
            $updated = $this->postRepository->update($post, [
                'pending_revision' => null,
                'revision_status' => null,
                'review_notes' => null,
            ]);

            return [
                'mode' => 'discarded',
                'post' => $updated,
            ];
        }

        $this->assertStatus($post, ['draft']);
        $this->delete($post);

        return [
            'mode' => 'deleted',
            'post' => null,
        ];
    }

    public function publishScheduled(): int
    {
        $duePosts = $this->postRepository->getDueScheduledPosts();
        $count = $this->postRepository->publishDue();

        foreach ($duePosts as $post) {
            $this->userNotificationService->notifyNewPost($post);
        }

        return $count;
    }

    private function savePendingRevision(Post $post, array $data, User $editor): Post
    {
        $revision = is_array($post->pending_revision) && $post->pending_revision !== []
            ? $post->pending_revision
            : $post->liveContentSnapshot();

        foreach (['title', 'body', 'category_id', 'youtube_url'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            // youtube_url may be explicitly null to clear it from the pending revision.
            if ($field === 'youtube_url' || $data[$field] !== null) {
                $revision[$field] = $data[$field];
            }
        }

        if (array_key_exists('comments_enabled', $data)) {
            $revision['comments_enabled'] = (bool) $data['comments_enabled'];
        }

        if (array_key_exists('is_featured', $data)) {
            $revision['is_featured'] = (bool) $data['is_featured'];
        }

        if (($data['media_file'] ?? null) instanceof UploadedFile) {
            $previousRevisionMedia = $revision['media_url'] ?? null;
            $liveMedia = $post->media_url;
            $newMedia = $this->storeMediaFile($data['media_file']);
            $revision['media_url'] = $newMedia;

            if ($previousRevisionMedia && $previousRevisionMedia !== $liveMedia) {
                $this->deleteStoredMedia($previousRevisionMedia);
            }
        }

        // Editor edits on live posts go straight to publisher review + inbox notify.
        $post = $this->postRepository->update($post, [
            'pending_revision' => $revision,
            'revision_status' => 'pending_review',
            'review_notes' => null,
        ]);

        $this->adminNotificationService->notifyPostSubmitted($post, $editor);

        return $post;
    }

    private function applyPendingRevision(Post $post, User $publisher): Post
    {
        $revision = $post->pending_revision;

        if (! is_array($revision) || $revision === []) {
            abort(422, 'This published post has no pending update to approve.');
        }

        $previousLiveMedia = $post->media_url;
        $nextMedia = array_key_exists('media_url', $revision) ? $revision['media_url'] : $previousLiveMedia;

        $post = $this->postRepository->update($post, [
            'title' => $revision['title'] ?? $post->title,
            'body' => $revision['body'] ?? $post->body,
            'category_id' => $revision['category_id'] ?? $post->category_id,
            'media_url' => $nextMedia,
            'youtube_url' => array_key_exists('youtube_url', $revision)
                ? $revision['youtube_url']
                : $post->youtube_url,
            'comments_enabled' => array_key_exists('comments_enabled', $revision)
                ? (bool) $revision['comments_enabled']
                : (bool) $post->comments_enabled,
            'is_featured' => array_key_exists('is_featured', $revision)
                ? (bool) $revision['is_featured']
                : (bool) $post->is_featured,
            'pending_revision' => null,
            'revision_status' => null,
            'review_notes' => null,
        ]);

        if ($previousLiveMedia && $previousLiveMedia !== $post->media_url) {
            $this->deleteStoredMedia($previousLiveMedia);
        }

        $this->adminNotificationService->notifyPostScheduled($post, $publisher);

        return $post;
    }

    private function publisherCanView(Post $post): bool
    {
        return in_array($post->status, ['pending_review', 'scheduled'], true)
            || ($post->status === 'published'
                && $post->revision_status === 'pending_review'
                && $post->hasPendingRevision());
    }

    private function resolveSuperAdminPublishMeta(User $author, ?string $requestedStatus = null): array
    {
        if ($author->role !== 'super_admin') {
            return [
                'status'       => 'draft',
                'publish_date' => null,
            ];
        }

        if (in_array($requestedStatus, ['archived', 'scheduled'], true)) {
            return [
                'status'       => $requestedStatus,
                'publish_date' => $requestedStatus === 'scheduled' ? null : Carbon::now(),
            ];
        }

        return [
            'status'       => 'published',
            'publish_date' => Carbon::now(),
        ];
    }

    private function applySuperAdminPublishDefaults(array $payload, Post $post): array
    {
        $requestedStatus = $payload['status'] ?? $post->status;

        if (in_array($requestedStatus, ['archived', 'scheduled'], true)) {
            $payload['status'] = $requestedStatus;

            return $payload;
        }

        $payload['status'] = 'published';
        $payload['publish_date'] = $post->publish_date ?? Carbon::now();

        return $payload;
    }

    private function payloadTouchesPostContent(array $payload): bool
    {
        foreach (['title', 'body', 'category_id', 'media_url', 'youtube_url', 'status', 'media_file', 'publish_date'] as $key) {
            if (array_key_exists($key, $payload)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyWordBlocking(array $data): array
    {
        if (array_key_exists('title', $data) && is_string($data['title'])) {
            $data['title'] = $this->blockedWordService->mask($data['title']);
        }

        if (array_key_exists('body', $data) && is_string($data['body'])) {
            $data['body'] = $this->blockedWordService->maskHtml(
                SafeHtml::sanitize($data['body']) ?? ''
            );
        }

        return $data;
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
