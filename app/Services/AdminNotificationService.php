<?php

namespace App\Services;

use App\Models\AdminNotification;
use App\Models\Post;
use App\Models\User;
use App\Repositories\AdminNotificationRepository;
use App\Support\ContentWorkflow\ContentWorkflowSections;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

class AdminNotificationService
{
    public function __construct(
        private readonly AdminNotificationRepository $repository,
    ) {
    }

    public function listForUser(User $user, int $perPage = 10, bool $unreadOnly = false): LengthAwarePaginator
    {
        return $this->repository->paginateForUser($user, $perPage, $unreadOnly);
    }

    public function unreadSummary(User $user): array
    {
        return [
            'unread_count' => $this->repository->countUnreadForUser($user),
        ];
    }

    public function markAsRead(AdminNotification $notification, User $user): void
    {
        abort_if($notification->user_id !== $user->id, 403, 'You cannot modify this notification.');

        $this->repository->markAsRead($notification);
    }

    public function markAllAsRead(User $user): int
    {
        return $this->repository->markAllAsRead($user);
    }

    public function notifyPostRejected(Post $post, User $publisher, string $reviewNotes): void
    {
        $author = $post->author;

        if (! $author) {
            return;
        }

        $this->repository->createForUser($author, [
            'type' => 'post_rejected',
            'title' => 'Post returned for revision',
            'body' => $reviewNotes,
            'data' => $this->postPayload($post, $publisher->name),
        ]);
    }

    public function notifyPostScheduled(Post $post, User $publisher): void
    {
        $author = $post->author;

        if (! $author) {
            return;
        }

        $isLive = $post->status === 'published';

        $this->repository->createForUser($author, [
            'type' => $isLive ? 'post_published' : 'post_scheduled',
            'title' => $isLive ? 'Post published' : 'Post approved and scheduled',
            'body' => $isLive
                ? sprintf(
                    'Your post "%s" was published by %s.',
                    $post->title,
                    $publisher->name,
                )
                : sprintf(
                    'Your post "%s" was scheduled to publish on %s by %s.',
                    $post->title,
                    optional($post->publish_date)->format('M d, Y H:i') ?? 'a future date',
                    $publisher->name,
                ),
            'data' => $this->postPayload($post, $publisher->name),
        ]);
    }

    public function notifyPostSubmitted(Post $post, User $editor): void
    {
        $recipients = User::query()
            ->whereIn('role', ['publisher', 'super_admin'])
            ->where('id', '!=', $editor->id)
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        $isUpdate = $post->isRevisionAwaitingPublisher();
        $proposedTitle = is_array($post->pending_revision) && filled($post->pending_revision['title'] ?? null)
            ? (string) $post->pending_revision['title']
            : $post->title;

        foreach ($recipients as $recipient) {
            $this->repository->createForUser($recipient, [
                'type' => 'post_submitted',
                'title' => $isUpdate ? 'Post update submitted for review' : 'Post submitted for review',
                'body' => $isUpdate
                    ? sprintf(
                        '%s submitted an update to "%s" for publisher review. The live post is unchanged until approved.',
                        $editor->name,
                        $proposedTitle,
                    )
                    : sprintf(
                        '%s submitted "%s" for publisher review.',
                        $editor->name,
                        $post->title,
                    ),
                'data' => $this->postPayload($post, $editor->name),
            ]);
        }
    }

    public function notifyContentSubmitted(string $section, Model $model, User $editor): void
    {
        $meta = ContentWorkflowSections::get($section);
        $label = $meta['label'] ?? 'Item';
        $title = method_exists($model, 'workflowTitle') ? $model->workflowTitle() : (string) $model->getKey();
        $isUpdate = method_exists($model, 'isRevisionAwaitingPublisher') && $model->isRevisionAwaitingPublisher();

        $recipients = User::query()
            ->whereIn('role', ['publisher', 'super_admin'])
            ->where('id', '!=', $editor->id)
            ->get();

        foreach ($recipients as $recipient) {
            $this->repository->createForUser($recipient, [
                'type' => 'content_submitted',
                'title' => $isUpdate ? $label.' update submitted for review' : $label.' submitted for review',
                'body' => $isUpdate
                    ? sprintf(
                        '%s submitted an update to "%s" for publisher review. The live version is unchanged until approved.',
                        $editor->name,
                        $title,
                    )
                    : sprintf('%s submitted "%s" for publisher review.', $editor->name, $title),
                'data' => $this->contentPayload($section, $model, $editor->name, $title),
            ]);
        }
    }

    public function notifyContentRejected(string $section, Model $model, User $publisher, string $reviewNotes): void
    {
        $authorId = (int) ($model->getAttribute('authored_by') ?? 0);
        $author = $authorId > 0 ? User::query()->find($authorId) : null;

        if (! $author) {
            return;
        }

        $meta = ContentWorkflowSections::get($section);
        $label = $meta['label'] ?? 'Item';
        $title = method_exists($model, 'workflowTitle') ? $model->workflowTitle() : (string) $model->getKey();

        $this->repository->createForUser($author, [
            'type' => 'content_rejected',
            'title' => $label.' returned for revision',
            'body' => $reviewNotes,
            'data' => array_merge(
                $this->contentPayload($section, $model, $publisher->name, $title),
                ['review_notes' => $reviewNotes],
            ),
        ]);
    }

    public function notifyContentPublished(string $section, Model $model, User $publisher): void
    {
        $authorId = (int) ($model->getAttribute('authored_by') ?? 0);
        $author = $authorId > 0 ? User::query()->find($authorId) : null;

        if (! $author || $author->id === $publisher->id) {
            return;
        }

        $meta = ContentWorkflowSections::get($section);
        $label = $meta['label'] ?? 'Item';
        $title = method_exists($model, 'workflowTitle') ? $model->workflowTitle() : (string) $model->getKey();

        $this->repository->createForUser($author, [
            'type' => 'content_published',
            'title' => $label.' published',
            'body' => sprintf('Your %s "%s" was published by %s.', strtolower($label), $title, $publisher->name),
            'data' => $this->contentPayload($section, $model, $publisher->name, $title),
        ]);
    }

    /**
     * @return array{post_id:int, post_title:string, actor_name:string, admin_url:string}
     */
    private function postPayload(Post $post, string $actorName): array
    {
        return [
            'post_id' => $post->id,
            'post_title' => $post->title,
            'actor_name' => $actorName,
            'admin_url' => '/admin/posts?post='.$post->id,
            'section' => 'posts',
            'resource_id' => $post->id,
        ];
    }

    /**
     * @return array{section:string, resource_id:int|string, title:string, actor_name:string, admin_url:string}
     */
    private function contentPayload(string $section, Model $model, string $actorName, string $title): array
    {
        $id = $section === 'legal-pages' && filled($model->getAttribute('slug'))
            ? (string) $model->getAttribute('slug')
            : $model->getKey();

        return [
            'section' => $section,
            'resource_id' => $id,
            'title' => $title,
            'post_title' => $title,
            'actor_name' => $actorName,
            'admin_url' => ContentWorkflowSections::adminUrl($section, $id),
        ];
    }

    public function notifyUserCreated(User $user, User $actor): void
    {
        $this->repository->createForUser($user, [
            'type' => 'user_created',
            'title' => 'Welcome to DAPE-MA Admin',
            'body' => sprintf(
                '%s created this admin account with role %s.',
                $actor->name,
                $user->role,
            ),
            'data' => [
                'actor_name' => $actor->name,
                'role' => $user->role,
                'admin_url' => '/admin/dashboard',
            ],
        ]);
    }

    public function notifyUserRoleChanged(User $user, User $actor, string $oldRole, string $newRole): void
    {
        $this->repository->createForUser($user, [
            'type' => 'user_role_changed',
            'title' => 'Your admin role was updated',
            'body' => sprintf(
                'Your role was changed from %s to %s by %s.',
                $oldRole,
                $newRole,
                $actor->name,
            ),
            'data' => [
                'actor_name' => $actor->name,
                'old_role' => $oldRole,
                'new_role' => $newRole,
                'admin_url' => '/admin/dashboard',
            ],
        ]);
    }
}

