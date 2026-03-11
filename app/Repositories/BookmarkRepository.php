<?php

namespace App\Repositories;

use App\Models\Bookmark;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class BookmarkRepository
{
    public function listForUser(User $user): Collection
    {
        return Post::query()
            ->with(['category', 'author'])
            ->whereHas('bookmarks', fn ($q) => $q->where('user_id', $user->id))
            ->where('status', 'published')
            ->latest('publish_date')
            ->get();
    }

    public function toggle(User $user, Post $post): Bookmark
    {
        $existing = Bookmark::query()
            ->where('user_id', $user->id)
            ->where('post_id', $post->id)
            ->first();

        if ($existing) {
            $existing->delete();

            return $existing;
        }

        return Bookmark::create([
            'user_id' => $user->id,
            'post_id' => $post->id,
        ]);
    }
}

