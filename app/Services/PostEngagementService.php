<?php

namespace App\Services;

use App\Models\Post;
use App\Models\PostComment;
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
        if (! $user) {
            $posts->getCollection()->transform(function (Post $post) {
                $post->setAttribute('is_liked', false);

                return $post;
            });

            return $posts;
        }

        $likedIds = $this->postEngagementRepository->likedPostIdsForUser(
            $user,
            $posts->getCollection()->pluck('id'),
        );

        $likedLookup = array_fill_keys($likedIds, true);

        $posts->getCollection()->transform(function (Post $post) use ($likedLookup) {
            $post->setAttribute('is_liked', isset($likedLookup[$post->id]));

            return $post;
        });

        return $posts;
    }

    public function attachLikedStateToPost(Post $post, ?User $user): Post
    {
        if (! $user) {
            $post->setAttribute('is_liked', false);

            return $post;
        }

        $likedIds = $this->postEngagementRepository->likedPostIdsForUser(
            $user,
            collect([$post->id]),
        );

        $post->setAttribute('is_liked', in_array($post->id, $likedIds, true));

        return $post;
    }

    public function toggleLike(User $user, int $postId): array
    {
        $post = $this->postRepository->findOrFail($postId);

        if ($post->status !== 'published') {
            abort(422, 'Only published posts can be liked.');
        }

        return $this->postEngagementRepository->toggleLike($user, $post);
    }

    public function listComments(int $postId, int $perPage = 20): LengthAwarePaginator
    {
        $post = $this->postRepository->findOrFail($postId);

        if ($post->status !== 'published') {
            abort(404, 'Post not found.');
        }

        return $this->postEngagementRepository->listComments($post, $perPage);
    }

    public function createComment(User $user, int $postId, string $body): PostComment
    {
        $post = $this->postRepository->findOrFail($postId);

        if ($post->status !== 'published') {
            abort(422, 'Only published posts can be commented on.');
        }

        return $this->postEngagementRepository->createComment($user, $post, $body);
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

        return $this->postEngagementRepository->updateComment($comment, $body);
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
}
