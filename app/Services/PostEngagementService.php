<?php

namespace App\Services;

use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostLike;
use App\Models\User;
use App\Repositories\PostEngagementRepository;
use App\Repositories\PostRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Laravel\Sanctum\PersonalAccessToken;

class PostEngagementService
{
    public function __construct(
        private readonly PostEngagementRepository $postEngagementRepository,
        private readonly PostRepository $postRepository,
        private readonly ProfileService $profileService,
        private readonly UserNotificationService $userNotificationService,
    ) {
    }

    public function resolveUserFromBearer(?string $bearerToken): ?User
    {
        if (! $bearerToken) {
            return null;
        }

        $accessToken = PersonalAccessToken::findToken($bearerToken);

        if (! $accessToken) {
            return null;
        }

        if ($accessToken->expires_at && $accessToken->expires_at->isPast()) {
            return null;
        }

        $user = $accessToken->tokenable;

        return $user instanceof User ? $user : null;
    }

    public function attachLikedState(LengthAwarePaginator $posts, ?User $user): LengthAwarePaginator
    {
        $postIds = $posts->getCollection()->pluck('id');
        $countsByPost = $this->reactionCountsByPostIds($postIds);
        $userReactions = $user
            ? $this->postEngagementRepository->reactionsForUserPosts($user, $postIds)
            : [];

        $posts->getCollection()->transform(function (Post $post) use ($countsByPost, $userReactions) {
            $counts = $countsByPost[$post->id] ?? PostLike::emptyReactionCounts();
            $userReaction = $userReactions[$post->id]['reaction'] ?? null;

            $post->setAttribute('reaction_counts', $counts);
            $post->setAttribute('user_reaction', $userReaction);
            $post->setAttribute('is_liked', $userReaction !== null);
            $post->setAttribute('likes_count', array_sum($counts));

            return $post;
        });

        return $posts;
    }

    public function attachLikedStateToPost(Post $post, ?User $user): Post
    {
        $counts = $this->postEngagementRepository->reactionCountsForPost($post);
        $userReaction = null;

        if ($user) {
            $map = $this->postEngagementRepository->reactionsForUserPosts(
                $user,
                collect([$post->id]),
            );
            $userReaction = $map[$post->id]['reaction'] ?? null;
        }

        $post->setAttribute('reaction_counts', $counts);
        $post->setAttribute('user_reaction', $userReaction);
        $post->setAttribute('is_liked', $userReaction !== null);
        $post->setAttribute('likes_count', array_sum($counts));

        return $post;
    }

    public function toggleLike(User $user, int $postId, string $reaction = PostLike::REACTION_LIKE): array
    {
        $post = $this->postRepository->findOrFail($postId);

        if ($post->status !== 'published') {
            abort(422, 'Only published posts can be liked.');
        }

        $existing = $this->postEngagementRepository->reactionsForUserPosts(
            $user,
            collect([$postId]),
        );
        $hadReaction = isset($existing[$postId]);

        $result = $this->postEngagementRepository->toggleLike($user, $post, $reaction);

        if (($result['liked'] ?? false) === true && ! $hadReaction) {
            $post->loadMissing('author');
            $this->userNotificationService->notifyPostLiked($post, $user);
        }

        return $result;
    }

    public function listReactors(int $postId, ?string $reaction = null, int $perPage = 30): LengthAwarePaginator
    {
        $post = $this->postRepository->findOrFail($postId);

        if ($post->status !== 'published') {
            abort(404, 'Post not found.');
        }

        $paginator = $this->postEngagementRepository->listReactors($post, $reaction, $perPage);

        $paginator->getCollection()->transform(function (PostLike $like) {
            if ($like->relationLoaded('user') && $like->user) {
                $like->user->setAttribute(
                    'profile_image_url',
                    $this->profileService->profileImageUrl($like->user),
                );
            }

            return $like;
        });

        return $paginator;
    }

    /**
     * @param  Collection<int, int|string>  $postIds
     * @return array<int, array<string, int>>
     */
    private function reactionCountsByPostIds(Collection $postIds): array
    {
        if ($postIds->isEmpty()) {
            return [];
        }

        $rows = PostLike::query()
            ->selectRaw('post_id, reaction, COUNT(*) as aggregate')
            ->whereIn('post_id', $postIds)
            ->groupBy('post_id', 'reaction')
            ->get();

        $map = [];
        foreach ($postIds as $postId) {
            $map[(int) $postId] = PostLike::emptyReactionCounts();
        }

        foreach ($rows as $row) {
            $postId = (int) $row->post_id;
            $reaction = (string) $row->reaction;
            if (! isset($map[$postId])) {
                $map[$postId] = PostLike::emptyReactionCounts();
            }
            if (array_key_exists($reaction, $map[$postId])) {
                $map[$postId][$reaction] = (int) $row->aggregate;
            }
        }

        return $map;
    }

    public function listComments(int $postId, int $perPage = 20): LengthAwarePaginator
    {
        $post = $this->postRepository->findOrFail($postId);

        if ($post->status !== 'published') {
            abort(404, 'Post not found.');
        }

        $paginator = $this->postEngagementRepository->listComments($post, $perPage);
        $replyComments = $this->postEngagementRepository->listReplyCommentsForPost($post);

        $paginator->getCollection()->transform(function (PostComment $root) use ($replyComments) {
            $this->enrichCommentUser($root);
            $root->setAttribute(
                'replies',
                $this->buildReplyTree($replyComments, $root->id),
            );

            return $root;
        });

        return $paginator;
    }

    public function createComment(
        User $user,
        int $postId,
        string $body,
        ?int $parentId = null,
    ): PostComment {
        $post = $this->postRepository->findOrFail($postId);

        if ($post->status !== 'published') {
            abort(422, 'Only published posts can be commented on.');
        }

        if (! $post->comments_enabled) {
            abort(422, 'Comments are disabled for this post.');
        }

        $parent = null;
        if ($parentId !== null) {
            $parent = $this->postEngagementRepository->findCommentForPost($parentId, $postId);
        }

        $comment = $this->postEngagementRepository->createComment(
            $user,
            $post,
            $body,
            $parentId,
        );

        $post->loadMissing('author');

        if ($parent !== null) {
            $parent->loadMissing('user');
            $this->userNotificationService->notifyCommentReply($comment, $parent, $post, $user);
        } else {
            $this->userNotificationService->notifyPostComment($comment, $post, $user);
        }

        $this->enrichCommentUser($comment);
        $comment->setAttribute('replies', []);

        return $comment;
    }

    public function updateComment(User $user, int $postId, int $commentId, string $body): PostComment
    {
        $post = $this->postRepository->findOrFail($postId);

        if ($post->status !== 'published') {
            abort(404, 'Post not found.');
        }

        $comment = $this->postEngagementRepository->findCommentForPost($commentId, $postId);

        if ($comment->user_id !== $user->id) {
            abort(403, 'You can only edit your own comments.');
        }

        $updated = $this->postEngagementRepository->updateComment($comment, $body);
        $this->enrichCommentUser($updated);

        return $updated;
    }

    public function deleteComment(User $user, int $postId, int $commentId): int
    {
        $post = $this->postRepository->findOrFail($postId);

        if ($post->status !== 'published') {
            abort(404, 'Post not found.');
        }

        $comment = $this->postEngagementRepository->findCommentForPost($commentId, $postId);

        if ($comment->user_id !== $user->id) {
            abort(403, 'You can only delete your own comments.');
        }

        $this->postEngagementRepository->deleteComment($comment);

        return $post->comments()->count();
    }

    /**
     * @return array<int, PostComment>
     */
    private function buildReplyTree(Collection $comments, int $parentId): array
    {
        return $comments
            ->where('parent_id', $parentId)
            ->map(function (PostComment $comment) use ($comments) {
                $this->enrichCommentUser($comment);
                $comment->setAttribute(
                    'replies',
                    $this->buildReplyTree($comments, $comment->id),
                );

                return $comment;
            })
            ->values()
            ->all();
    }

    private function enrichCommentUser(PostComment $comment): void
    {
        if (! $comment->relationLoaded('user') || ! $comment->user) {
            return;
        }

        $comment->user->setAttribute(
            'profile_image_url',
            $this->profileService->profileImageUrl($comment->user),
        );
    }
}
