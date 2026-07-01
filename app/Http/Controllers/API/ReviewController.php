<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Review\StoreReviewRequest;
use App\Services\PostEngagementService;
use App\Services\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function __construct(
        private readonly ReviewService $reviewService,
        private readonly PostEngagementService $postEngagementService,
    ) {
    }

    public function indexForPost(Request $request, int $id): JsonResponse
    {
        $user = $this->postEngagementService->resolveUserFromBearer($request->bearerToken());
        $limit = (int) $request->integer('limit', 20);

        return response()->json([
            'status'  => true,
            'message' => 'Reviews fetched successfully.',
            'data'    => [
                'summary' => $this->reviewService->summaryForPost($id, $user),
                'reviews' => $this->reviewService->listForPost($id, $limit),
            ],
        ]);
    }

    public function store(StoreReviewRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        if ($data['target_type'] === 'post') {
            $review = $this->reviewService->createForPost(
                $user,
                (int) $data['target_id'],
                (int) $data['rating'],
                $data['comment'] ?? null,
            );

            $summary = $this->reviewService->summaryForPost((int) $data['target_id'], $user);
        } else {
            $review = $this->reviewService->createForRehabCenter(
                $user,
                (int) $data['target_id'],
                (int) $data['rating'],
                $data['comment'] ?? null,
            );

            $summary = [
                'average_rating' => 0,
                'reviews_count'  => 0,
                'user_review'    => null,
            ];
        }

        return response()->json([
            'status'  => true,
            'message' => 'Review submitted successfully.',
            'data'    => [
                'review'  => [
                    'id'      => $review->id,
                    'rating'  => $review->rating,
                    'comment' => $review->comment,
                ],
                'summary' => $summary,
            ],
        ]);
    }
}
