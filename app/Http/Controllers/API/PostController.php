<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\PostEngagementService;
use App\Services\PostService;
use App\Services\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function __construct(
        private readonly PostService $postService,
        private readonly PostEngagementService $postEngagementService,
        private readonly ReviewService $reviewService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 20);
        $category = $request->get('category');
        $categorySlug = is_string($category) && $category !== '' ? $category : null;

        $posts = $this->postService->listPublished($perPage, $categorySlug);
        $user = $this->postEngagementService->resolveUserFromBearer($request->bearerToken());
        $posts = $this->postEngagementService->attachLikedState($posts, $user);
        $posts = $this->reviewService->attachReviewState($posts, $user);

        return response()->json([
            'status'  => true,
            'message' => 'Posts fetched successfully.',
            'data'    => $posts,
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $post = $this->postService->find($id);

        if ($post->status !== 'published') {
            return response()->json([
                'status'  => false,
                'message' => 'Post not found.',
            ], 404);
        }

        $user = $this->postEngagementService->resolveUserFromBearer($request->bearerToken());
        $post = $this->postEngagementService->attachLikedStateToPost($post, $user);
        $post = $this->reviewService->attachReviewStateToPost($post, $user);

        return response()->json([
            'status' => true,
            'data'   => $post,
        ]);
    }
}
