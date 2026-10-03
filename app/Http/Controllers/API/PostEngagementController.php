<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Post\SetPostReactionRequest;
use App\Http\Requests\Post\StorePostCommentRequest;
use App\Http\Requests\Post\UpdatePostCommentRequest;
use App\Models\PostLike;
use App\Services\PostEngagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostEngagementController extends Controller
{
    public function __construct(
        private readonly PostEngagementService $postEngagementService,
    ) {
    }

    public function toggleLike(SetPostReactionRequest $request, int $id): JsonResponse
    {
        $reaction = $request->validated('reaction') ?? PostLike::REACTION_LIKE;
        $result = $this->postEngagementService->toggleLike(
            $request->user(),
            $id,
            $reaction,
        );

        $message = 'Post reaction updated.';
        if (! ($result['liked'] ?? false)) {
            $message = 'Reaction removed.';
        } elseif (($result['user_reaction'] ?? null) === PostLike::REACTION_LIKE) {
            $message = 'Post liked.';
        }

        return response()->json([
            'status' => true,
            'message' => $message,
            'data' => $result,
        ]);
    }

    public function reactions(int $id, Request $request): JsonResponse
    {
        $perPage = max(1, min(100, (int) $request->integer('per_page', 30)));
        $type = $request->query('type');
        $type = is_string($type) && in_array($type, PostLike::REACTION_TYPES, true)
            ? $type
            : null;

        $reactors = $this->postEngagementService->listReactors($id, $type, $perPage);

        return response()->json([
            'status' => true,
            'message' => 'Reactors fetched successfully.',
            'data' => $reactors,
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
        $body = trim((string) ($request->validated('body') ?? ''));

        $comment = $this->postEngagementService->createComment(
            $request->user(),
            $id,
            $body,
            $request->validated('parent_id'),
            $request->file('image'),
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
