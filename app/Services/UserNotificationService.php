<?php

namespace App\Services;

use App\Models\Post;
use App\Models\PostComment;
use App\Models\RehabCenter;
use App\Models\Training;
use App\Models\User;
use App\Models\UserNotification;
use App\Repositories\UserNotificationRepository;
use App\Support\ContentWorkflow\ContentWorkflowMobileNotify;
use App\Support\Notification\NotificationLinkType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

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
                'link_type' => 'post',
                'link_id' => $post->id,
            ],
        ]);
    }

    /**
     * Inbox alert for app users when Hope / Contest / IEC / Care Support / Directory go live.
     */
    public function notifyPublishedContent(string $section, Model $model): void
    {
        if (! ContentWorkflowMobileNotify::shouldNotify($section)) {
            return;
        }

        $recipients = User::query()
            ->where('role', 'app_user')
            ->get(['id']);

        if ($recipients->isEmpty()) {
            return;
        }

        $itemTitle = method_exists($model, 'workflowTitle')
            ? $model->workflowTitle()
            : (string) ($model->getAttribute('title')
                ?? $model->getAttribute('title_en')
                ?? $model->getAttribute('name')
                ?? $model->getKey());

        $copy = ContentWorkflowMobileNotify::copy($section, $itemTitle);
        $linkId = (int) $model->getKey();

        $this->repository->createForUsers($recipients, [
            'type' => $copy['type'],
            'title' => $copy['title'],
            'body' => $copy['body'],
            'data' => [
                'link_type' => $copy['link_type'],
                'link_id' => $linkId,
                'section' => $section,
                'title' => $itemTitle,
                'hope_event_id' => $copy['link_type'] === NotificationLinkType::HOPE_EVENT ? $linkId : null,
            ],
        ]);
    }

    public function notifyNewRehabCenter(RehabCenter $center): void
    {
        $this->notifyDirectoryItem(
            type: 'new_rehab_center',
            linkType: NotificationLinkType::REHAB_CENTER,
            title: 'New treatment center',
            body: sprintf('"%s" was added to Treatment Rehabilitation Centers.', $center->name),
            linkId: (int) $center->id,
            itemTitle: (string) $center->name,
        );
    }

    public function notifyNewTraining(Training $training): void
    {
        $this->notifyDirectoryItem(
            type: 'new_training',
            linkType: NotificationLinkType::TRAINING,
            title: 'New training',
            body: sprintf('"%s" is now available.', $training->title),
            linkId: (int) $training->id,
            itemTitle: (string) $training->title,
        );
    }

    private function notifyDirectoryItem(
        string $type,
        string $linkType,
        string $title,
        string $body,
        int $linkId,
        string $itemTitle,
    ): void {
        $recipients = User::query()
            ->where('role', 'app_user')
            ->get(['id']);

        if ($recipients->isEmpty()) {
            return;
        }

        $this->repository->createForUsers($recipients, [
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => [
                'link_type' => $linkType,
                'link_id' => $linkId,
                'title' => $itemTitle,
            ],
        ]);
    }
}
