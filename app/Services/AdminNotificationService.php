<?php

namespace App\Services;

use App\Models\AdminNotification;
use App\Models\Post;
use App\Models\User;
use App\Repositories\AdminNotificationRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

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
            'title' => 'Post rejected for revision',
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

        foreach ($recipients as $recipient) {
            $this->repository->createForUser($recipient, [
                'type' => 'post_submitted',
                'title' => 'Post submitted for review',
                'body' => sprintf(
                    '%s submitted "%s" for publisher review.',
                    $editor->name,
                    $post->title,
                ),
                'data' => $this->postPayload($post, $editor->name),
            ]);
        }
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

