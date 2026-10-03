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
    public function toggleLike(User $user, Post $post, string $reaction = PostLike::REACTION_LIKE): array
    {
        $reaction = in_array($reaction, PostLike::REACTION_TYPES, true)
            ? $reaction
            : PostLike::REACTION_LIKE;

        $existing = PostLike::query()
            ->where('user_id', $user->id)
            ->where('post_id', $post->id)
            ->first();

        $liked = true;
        $userReaction = $reaction;

        if ($existing) {
            if ($existing->reaction === $reaction) {
                $existing->delete();
                $liked = false;
                $userReaction = null;
            } else {
                $existing->update(['reaction' => $reaction]);
            }
        } else {
            PostLike::create([
                'user_id' => $user->id,
                'post_id' => $post->id,
                'reaction' => $reaction,
            ]);
        }

        return $this->buildReactionPayload($post, $liked, $userReaction);
    }

    public function reactionCountsForPost(Post $post): array
    {
        $counts = PostLike::emptyReactionCounts();

        PostLike::query()
            ->selectRaw('reaction, COUNT(*) as aggregate')
            ->where('post_id', $post->id)
            ->groupBy('reaction')
            ->get()
            ->each(function ($row) use (&$counts) {
                $key = (string) $row->reaction;
                if (array_key_exists($key, $counts)) {
                    $counts[$key] = (int) $row->aggregate;
                }
            });

        return $counts;
    }

    /**
     * @param  Collection<int, int|string>  $postIds
     * @return array<int, array{reaction: string}>
     */
    public function reactionsForUserPosts(User $user, Collection $postIds): array
    {
        if ($postIds->isEmpty()) {
            return [];
        }

        return PostLike::query()
            ->where('user_id', $user->id)
            ->whereIn('post_id', $postIds)
            ->get(['post_id', 'reaction'])
            ->keyBy('post_id')
            ->map(fn (PostLike $like) => ['reaction' => $like->reaction])
            ->all();
    }

    public function listReactors(
        Post $post,
        ?string $reaction = null,
        int $perPage = 30,
    ): LengthAwarePaginator {
        $query = PostLike::query()
            ->with('user:id,name,profile_image_url')
            ->where('post_id', $post->id)
            ->latest();

        if ($reaction && in_array($reaction, PostLike::REACTION_TYPES, true)) {
            $query->where('reaction', $reaction);
        }

        return $query->paginate($perPage);
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

    private function buildReactionPayload(Post $post, bool $liked, ?string $userReaction): array
    {
        $reactionCounts = $this->reactionCountsForPost($post);
        $likesCount = array_sum($reactionCounts);

        return [
            'liked' => $liked,
            'likes_count' => $likesCount,
            'user_reaction' => $userReaction,
            'reaction_counts' => $reactionCounts,
        ];
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
        ?string $imagePath = null,
    ): PostComment {
        return PostComment::create([
            'user_id'    => $user->id,
            'post_id'    => $post->id,
            'parent_id'  => $parentId,
            'body'       => $body,
            'image_path' => $imagePath,
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
