<?php

namespace App\Services;

use App\Models\Post;
use App\Models\RehabCenter;
use App\Models\Review;
use App\Models\User;
use App\Repositories\ReviewRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ReviewService
{
    public function __construct(
        private readonly ReviewRepository $reviewRepository,
        private readonly ProfileService $profileService,
    ) {
    }

    public function createForPost(User $user, int $postId, int $rating, ?string $comment = null): Review
    {
        $post = Post::query()
            ->where('status', 'published')
            ->findOrFail($postId);

        return $this->reviewRepository->upsert($post, $user->id, $rating, $comment);
    }

    public function createForRehabCenter(User $user, int $centerId, int $rating, ?string $comment = null): Review
    {
        $center = RehabCenter::query()
            ->where('is_active', true)
            ->findOrFail($centerId);

        return $this->reviewRepository->upsert($center, $user->id, $rating, $comment);
    }

    public function listForPost(int $postId, int $limit = 20): Collection
    {
        Post::query()
            ->where('status', 'published')
            ->findOrFail($postId);

        return $this->reviewRepository
            ->listForTarget(Post::class, $postId, $limit)
            ->map(fn (Review $review) => $this->formatReview($review));
    }

    public function summaryForPost(int $postId, ?User $user = null): array
    {
        Post::query()
            ->where('status', 'published')
            ->findOrFail($postId);

        $average = $this->reviewRepository->averageRatingForTarget(Post::class, $postId);
        $count = $this->reviewRepository->countForTarget(Post::class, $postId);

        $userReview = null;

        if ($user) {
            $review = $this->reviewRepository->findForUserAndTarget(
                $user->id,
                Post::class,
                $postId,
            );

            if ($review) {
                $userReview = $this->formatReview($review);
            }
        }

        return [
            'average_rating' => round($average, 1),
            'reviews_count'  => $count,
            'user_review'    => $userReview,
        ];
    }

    public function attachReviewStateToPost(Post $post, ?User $user = null): Post
    {
        $average = (float) ($post->reviews_avg_rating ?? $post->getAttribute('reviews_avg_rating') ?? 0);
        $count = (int) ($post->reviews_count ?? $post->getAttribute('reviews_count') ?? 0);

        $post->setAttribute('average_rating', round($average, 1));
        $post->setAttribute('reviews_count', $count);
        $post->setAttribute('user_rating', null);
        $post->setAttribute('user_review_comment', null);

        if ($user) {
            $review = $this->reviewRepository->findForUserAndTarget(
                $user->id,
                Post::class,
                $post->id,
            );

            if ($review) {
                $post->setAttribute('user_rating', $review->rating);
                $post->setAttribute('user_review_comment', $review->comment);
            }
        }

        return $post;
    }

    public function attachReviewState(LengthAwarePaginator $posts, ?User $user): LengthAwarePaginator
    {
        $posts->getCollection()->transform(
            fn (Post $post) => $this->attachReviewStateToPost($post, $user),
        );

        return $posts;
    }

    private function formatReview(Review $review): array
    {
        $user = $review->user;

        return [
            'id'         => $review->id,
            'rating'     => $review->rating,
            'comment'    => $review->comment,
            'created_at' => $review->created_at?->toISOString(),
            'user'       => $user ? [
                'id'                => $user->id,
                'name'              => $user->name,
                'profile_image_url' => $this->profileService->profileImageUrl($user),
            ] : null,
        ];
    }
}
