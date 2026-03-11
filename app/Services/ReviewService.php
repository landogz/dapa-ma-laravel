<?php

namespace App\Services;

use App\Models\Post;
use App\Models\RehabCenter;
use App\Models\User;
use App\Repositories\ReviewRepository;

class ReviewService
{
    public function __construct(
        private readonly ReviewRepository $reviewRepository,
    ) {
    }

    public function createForPost(User $user, int $postId, int $rating, ?string $comment = null): void
    {
        $post = Post::query()
            ->where('status', 'published')
            ->findOrFail($postId);

        $this->reviewRepository->create($post, $user->id, $rating, $comment);
    }

    public function createForRehabCenter(User $user, int $centerId, int $rating, ?string $comment = null): void
    {
        $center = RehabCenter::query()
            ->where('is_active', true)
            ->findOrFail($centerId);

        $this->reviewRepository->create($center, $user->id, $rating, $comment);
    }
}

