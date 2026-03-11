<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\PostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function __construct(
        private readonly PostService $postService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 20);

        $posts = $this->postService->listPublished($perPage);

        return response()->json([
            'status'  => true,
            'message' => 'Posts fetched successfully.',
            'data'    => $posts,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $post = $this->postService->find($id);

        if ($post->status !== 'published') {
            return response()->json([
                'status'  => false,
                'message' => 'Post not found.',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data'   => $post,
        ]);
    }
}
