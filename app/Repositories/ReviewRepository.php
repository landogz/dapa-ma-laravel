<?php

namespace App\Repositories;

use App\Models\Review;
use Illuminate\Database\Eloquent\Model;

class ReviewRepository
{
    public function create(Model $target, int $userId, int $rating, ?string $comment = null): Review
    {
        return Review::create([
            'user_id'     => $userId,
            'target_id'   => $target->getKey(),
            'target_type' => get_class($target),
            'rating'      => $rating,
            'comment'     => $comment,
        ]);
    }
}

