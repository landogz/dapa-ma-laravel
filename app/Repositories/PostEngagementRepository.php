<?php

namespace App\Repositories;

use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostLike;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PostEngagementRepository
{
    public function toggleLike(User $user, Post $post): array
    {
        $existing = PostLike::query()
            ->where('user_id', $user->id)
            ->where('post_id', $post->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $liked = false;
        } else {
            PostLike::create([
                'user_id' => $user->id,
                'post_id' => $post->id,
            ]);
            $liked = true;
        }

        return [
            'liked'       => $liked,
            'likes_count' => $post->likes()->count(),
        ];
    }

    public function likedPostIdsForUser(User $user, Collection $postIds): array
    {
        if ($postIds->isEmpty()) {
            return [];
        }

        return PostLike::query()
            ->where('user_id', $user->id)
            ->whereIn('post_id', $postIds)
            ->pluck('post_id')
            ->all();
    }

    public function listComments(Post $post, int $perPage = 20): LengthAwarePaginator
    {
        return PostComment::query()
            ->with('user:id,name,profile_image_url')
            ->where('post_id', $post->id)
            ->whereNull('parent_id')
            ->latest()
            ->paginate($perPage);
    }

    public function listReplyCommentsForPost(Post $post): Collection
    {
        return PostComment::query()
            ->with('user:id,name,profile_image_url')
            ->where('post_id', $post->id)
            ->whereNotNull('parent_id')
            ->orderBy('created_at')
            ->get();
    }

    public function createComment(
        User $user,
        Post $post,
        string $body,
        ?int $parentId = null,
    ): PostComment {
        return PostComment::create([
            'user_id'   => $user->id,
            'post_id'   => $post->id,
            'parent_id' => $parentId,
            'body'      => $body,
        ])->load('user:id,name,profile_image_url');
    }

    public function findCommentForPost(int $commentId, int $postId): PostComment
    {
        return PostComment::query()
            ->with('user:id,name,profile_image_url')
            ->where('id', $commentId)
            ->where('post_id', $postId)
            ->firstOrFail();
    }

    public function updateComment(PostComment $comment, string $body): PostComment
    {
        $comment->update(['body' => $body]);

        return $comment->fresh(['user:id,name,profile_image_url']);
    }

    public function deleteComment(PostComment $comment): void
    {
        $comment->delete();
    }
}
