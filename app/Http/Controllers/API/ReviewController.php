<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReviewController extends Controller
{
    public function __construct(
        private readonly ReviewService $reviewService,
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'target_type' => [
                'required',
                Rule::in(['post', 'rehab_center']),
            ],
            'target_id' => ['required', 'integer'],
            'rating'    => ['required', 'integer', 'min:1', 'max:5'],
            'comment'   => ['nullable', 'string', 'max:2000'],
        ]);

        $user = $request->user();

        if ($data['target_type'] === 'post') {
            $this->reviewService->createForPost(
                $user,
                (int) $data['target_id'],
                (int) $data['rating'],
                $data['comment'] ?? null,
            );
        } else {
            $this->reviewService->createForRehabCenter(
                $user,
                (int) $data['target_id'],
                (int) $data['rating'],
                $data['comment'] ?? null,
            );
        }

        return response()->json([
            'status'  => true,
            'message' => 'Review submitted successfully.',
            'data'    => null,
        ]);
    }
}

