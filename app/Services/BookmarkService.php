<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\BookmarkRepository;
use App\Repositories\PostRepository;
use Illuminate\Database\Eloquent\Collection;

class BookmarkService
{
    public function __construct(
        private readonly BookmarkRepository $bookmarkRepository,
        private readonly PostRepository $postRepository,
    ) {
    }

    public function list(User $user): Collection
    {
        return $this->bookmarkRepository->listForUser($user);
    }

    public function toggle(User $user, int $postId): void
    {
        $post = $this->postRepository->findOrFail($postId);

        if ($post->status !== 'published') {
            abort(422, "Only published posts can be bookmarked.");
        }

        $this->bookmarkRepository->toggle($user, $post);
    }
}

