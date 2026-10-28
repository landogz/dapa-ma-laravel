<?php

namespace App\Http\Controllers\API\Admin\PostComment;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Services\PostEngagementService;
use Illuminate\Http\JsonResponse;

class PostCommentAdminController extends Controller
{
    public function __construct(
        private readonly PostEngagementService $postEngagementService,
    ) {
    }

    public function destroy(Post $post, int $comment): JsonResponse
    {
        $commentsCount = $this->postEngagementService->adminDeleteComment($post->id, $comment);

        return response()->json([
            'status' => true,
            'message' => 'Comment deleted successfully.',
            'data' => [
                'comments_count' => $commentsCount,
            ],
        ]);
    }
}
