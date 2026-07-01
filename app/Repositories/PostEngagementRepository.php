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
            ->with('user:id,name')
            ->where('post_id', $post->id)
            ->latest()
            ->paginate($perPage);
    }

    public function createComment(User $user, Post $post, string $body): PostComment
    {
        return PostComment::create([
            'user_id' => $user->id,
            'post_id' => $post->id,
            'body'    => $body,
        ])->load('user:id,name');
    }
}
