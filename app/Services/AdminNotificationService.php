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
            'data' => [
                'post_id' => $post->id,
                'post_title' => $post->title,
                'actor_name' => $publisher->name,
                'admin_url' => '/admin/posts',
            ],
        ]);
    }

    public function notifyPostScheduled(Post $post, User $publisher): void
    {
        $author = $post->author;

        if (! $author) {
            return;
        }

        $this->repository->createForUser($author, [
            'type' => 'post_scheduled',
            'title' => 'Post approved and scheduled',
            'body' => sprintf(
                'Your post "%s" was scheduled to publish on %s by %s.',
                $post->title,
                optional($post->publish_date)->format('M d, Y H:i') ?? 'a future date',
                $publisher->name,
            ),
            'data' => [
                'post_id' => $post->id,
                'post_title' => $post->title,
                'actor_name' => $publisher->name,
                'admin_url' => '/admin/posts',
            ],
        ]);
    }

    public function notifyPostSubmitted(Post $post, User $editor): void
    {
        $publishers = User::query()
            ->where('role', 'publisher')
            ->get();

        if ($publishers->isEmpty()) {
            return;
        }

        foreach ($publishers as $publisher) {
            $this->repository->createForUser($publisher, [
                'type' => 'post_submitted',
                'title' => 'Post submitted for review',
                'body' => sprintf(
                    '%s submitted "%s" for publisher review.',
                    $editor->name,
                    $post->title,
                ),
                'data' => [
                    'post_id' => $post->id,
                    'post_title' => $post->title,
                    'actor_name' => $editor->name,
                    'admin_url' => '/admin/posts',
                ],
            ]);
        }
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

