<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectPostRequest;
use App\Http\Requests\Admin\SchedulePostRequest;
use App\Http\Requests\Admin\StorePostRequest;
use App\Http\Requests\Admin\UpdatePostRequest;
use App\Models\Post;
use App\Services\PostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostAdminController extends Controller
{
    public function __construct(
        private readonly PostService $postService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 20);

        return response()->json([
            'status' => true,
            'data'   => $this->postService->listAdmin($perPage),
        ]);
    }

    public function options(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => 'Post options loaded successfully.',
            'data' => $this->postService->listForSelection(),
        ]);
    }

    public function show(Post $post): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data'   => $post->load(['category', 'author']),
        ]);
    }

    public function store(StorePostRequest $request): JsonResponse
    {
        $post = $this->postService->createDraft($request->validated(), $request->user());

        return response()->json([
            'status'  => true,
            'message' => 'Draft created successfully.',
            'data'    => $post,
        ], 201);
    }

    public function update(UpdatePostRequest $request, Post $post): JsonResponse
    {
        $post = $this->postService->updateDraft($post, $request->validated());

        return response()->json([
            'status'  => true,
            'message' => 'Post updated.',
            'data'    => $post,
        ]);
    }

    public function submit(Post $post): JsonResponse
    {
        $post = $this->postService->submitForReview($post, request()->user());

        return response()->json([
            'status'  => true,
            'message' => 'Post submitted for review.',
            'data'    => $post,
        ]);
    }

    public function reject(RejectPostRequest $request, Post $post): JsonResponse
    {
        $post = $this->postService->reject($post, $request->validated('review_notes'), $request->user());

        return response()->json([
            'status'  => true,
            'message' => 'Post rejected and returned to editor.',
            'data'    => $post,
        ]);
    }

    public function schedule(SchedulePostRequest $request, Post $post): JsonResponse
    {
        $post = $this->postService->schedule($post, $request->validated('publish_date'), $request->user());

        return response()->json([
            'status'  => true,
            'message' => 'Post scheduled for publishing.',
            'data'    => $post,
        ]);
    }

    public function publish(Post $post): JsonResponse
    {
        $post = $this->postService->publishNow($post, request()->user());

        return response()->json([
            'status'  => true,
            'message' => 'Post published.',
            'data'    => $post,
        ]);
    }

    public function archive(Post $post): JsonResponse
    {
        $post = $this->postService->archive($post);

        return response()->json([
            'status'  => true,
            'message' => 'Post archived.',
            'data'    => $post,
        ]);
    }

    public function destroy(Post $post): JsonResponse
    {
        $this->postService->delete($post);

        return response()->json([
            'status'  => true,
            'message' => 'Post deleted permanently.',
        ]);
    }
}
