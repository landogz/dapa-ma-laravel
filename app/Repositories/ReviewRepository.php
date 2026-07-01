<?php

namespace App\Repositories;

use App\Models\Review;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ReviewRepository
{
    public function upsert(Model $target, int $userId, int $rating, ?string $comment = null): Review
    {
        return Review::query()->updateOrCreate(
            [
                'user_id'     => $userId,
                'target_id'   => $target->getKey(),
                'target_type' => $target::class,
            ],
            [
                'rating'  => $rating,
                'comment' => $comment,
            ],
        );
    }

    public function findForUserAndTarget(int $userId, string $targetType, int $targetId): ?Review
    {
        return Review::query()
            ->where('user_id', $userId)
            ->where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->first();
    }

    public function listForTarget(string $targetType, int $targetId, int $perPage = 20): Collection
    {
        return Review::query()
            ->with('user:id,name,profile_image_url')
            ->where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->latest()
            ->limit($perPage)
            ->get();
    }

    public function averageRatingForTarget(string $targetType, int $targetId): float
    {
        return (float) Review::query()
            ->where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->avg('rating');
    }

    public function countForTarget(string $targetType, int $targetId): int
    {
        return Review::query()
            ->where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->count();
    }
}
