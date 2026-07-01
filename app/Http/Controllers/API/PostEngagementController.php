<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Post\StorePostCommentRequest;
use App\Http\Requests\Post\UpdatePostCommentRequest;
use App\Services\PostEngagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostEngagementController extends Controller
{
    public function __construct(
        private readonly PostEngagementService $postEngagementService,
    ) {
    }

    public function toggleLike(Request $request, int $id): JsonResponse
    {
        $result = $this->postEngagementService->toggleLike($request->user(), $id);

        return response()->json([
            'status'  => true,
            'message' => $result['liked'] ? 'Post liked.' : 'Post unliked.',
            'data'    => $result,
        ]);
    }

    public function comments(int $id, Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 20);
        $comments = $this->postEngagementService->listComments($id, $perPage);

        return response()->json([
            'status'  => true,
            'message' => 'Comments fetched successfully.',
            'data'    => $comments,
        ]);
    }

    public function storeComment(StorePostCommentRequest $request, int $id): JsonResponse
    {
        $comment = $this->postEngagementService->createComment(
            $request->user(),
            $id,
            $request->validated('body'),
            $request->validated('parent_id'),
        );

        return response()->json([
            'status'  => true,
            'message' => 'Comment posted successfully.',
            'data'    => $comment,
        ], 201);
    }

    public function updateComment(
        UpdatePostCommentRequest $request,
        int $id,
        int $commentId,
    ): JsonResponse {
        $comment = $this->postEngagementService->updateComment(
            $request->user(),
            $id,
            $commentId,
            $request->validated('body'),
        );

        return response()->json([
            'status'  => true,
            'message' => 'Comment updated successfully.',
            'data'    => $comment,
        ]);
    }

    public function destroyComment(Request $request, int $id, int $commentId): JsonResponse
    {
        $commentsCount = $this->postEngagementService->deleteComment(
            $request->user(),
            $id,
            $commentId,
        );

        return response()->json([
            'status'  => true,
            'message' => 'Comment deleted successfully.',
            'data'    => [
                'comments_count' => $commentsCount,
            ],
        ]);
    }
}
