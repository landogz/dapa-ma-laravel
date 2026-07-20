<?php

namespace App\Services;

use App\Models\Post;
use App\Models\PostComment;
use App\Models\User;
use App\Models\UserNotification;
use App\Repositories\UserNotificationRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserNotificationService
{
    public function __construct(
        private readonly UserNotificationRepository $repository,
    ) {
    }

    public function listForUser(User $user, int $perPage = 20, bool $unreadOnly = false): LengthAwarePaginator
    {
        return $this->repository->paginateForUser($user, $perPage, $unreadOnly);
    }

    public function unreadSummary(User $user): array
    {
        return [
            'unread_count' => $this->repository->countUnreadForUser($user),
        ];
    }

    public function markAsRead(UserNotification $notification, User $user): void
    {
        abort_if($notification->user_id !== $user->id, 403, 'You cannot modify this notification.');

        $this->repository->markAsRead($notification);
    }

    public function markAllAsRead(User $user): int
    {
        return $this->repository->markAllAsRead($user);
    }

    public function notifyCommentReply(PostComment $reply, PostComment $parent, Post $post, User $actor): void
    {
        $recipient = $parent->user;

        if (! $recipient || $recipient->id === $actor->id) {
            return;
        }

        $snippet = mb_strimwidth(trim($reply->body), 0, 80, '…');

        $this->repository->createForUser($recipient, [
            'type' => 'comment_reply',
            'title' => 'New reply to your comment',
            'body' => sprintf('%s replied: "%s"', $actor->name, $snippet),
            'data' => [
                'post_id' => $post->id,
                'post_title' => $post->title,
                'comment_id' => $reply->id,
                'parent_comment_id' => $parent->id,
                'actor_id' => $actor->id,
                'actor_name' => $actor->name,
            ],
        ]);
    }

    public function notifyPostComment(PostComment $comment, Post $post, User $actor): void
    {
        $author = $post->author;

        if (! $author || $author->id === $actor->id) {
            return;
        }

        $snippet = mb_strimwidth(trim($comment->body), 0, 80, '…');

        $this->repository->createForUser($author, [
            'type' => 'post_comment',
            'title' => 'New comment on your post',
            'body' => sprintf('%s commented on "%s": "%s"', $actor->name, $post->title, $snippet),
            'data' => [
                'post_id' => $post->id,
                'post_title' => $post->title,
                'comment_id' => $comment->id,
                'actor_id' => $actor->id,
                'actor_name' => $actor->name,
            ],
        ]);
    }

    public function notifyPostLiked(Post $post, User $actor): void
    {
        $author = $post->author;

        if (! $author || $author->id === $actor->id) {
            return;
        }

        $this->repository->createForUser($author, [
            'type' => 'post_liked',
            'title' => 'Someone liked your post',
            'body' => sprintf('%s liked "%s".', $actor->name, $post->title),
            'data' => [
                'post_id' => $post->id,
                'post_title' => $post->title,
                'actor_id' => $actor->id,
                'actor_name' => $actor->name,
            ],
        ]);
    }

    public function notifyNewPost(Post $post): void
    {
        $recipients = User::query()
            ->where('role', 'app_user')
            ->get(['id']);

        if ($recipients->isEmpty()) {
            return;
        }

        // Exclude the post author if they are somehow an app_user.
        $recipients = $recipients->reject(fn (User $user) => $user->id === $post->author_id);

        $this->repository->createForUsers($recipients, [
            'type' => 'new_post',
            'title' => 'New post published',
            'body' => sprintf('"%s" is now available.', $post->title),
            'data' => [
                'post_id' => $post->id,
                'post_title' => $post->title,
            ],
        ]);
    }
}
