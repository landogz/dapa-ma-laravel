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
            'data'   => $this->postService->listAdmin($perPage, $request->user()),
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
            'status'  => true,
            'message' => 'Post loaded successfully.',
            'data'    => $this->postService->findAdminDetail($post->id, request()->user()),
        ]);
    }

    public function store(StorePostRequest $request): JsonResponse
    {
        $post = $this->postService->createDraft($request->validated(), $request->user());
        $isSuperAdmin = $request->user()?->role === 'super_admin';

        return response()->json([
            'status'  => true,
            'message' => $isSuperAdmin
                ? 'Post published successfully.'
                : 'Draft created successfully.',
            'data'    => $post,
        ], 201);
    }

    public function update(UpdatePostRequest $request, Post $post): JsonResponse
    {
        $wasPublishedLive = $post->status === 'published';
        $post = $this->postService->updateDraft($post, $request->validated(), $request->user());
        $isSuperAdmin = $request->user()?->role === 'super_admin';
        $savedAsRevision = ! $isSuperAdmin
            && $wasPublishedLive
            && $post->status === 'published'
            && $post->revision_status === 'draft';

        return response()->json([
            'status'  => true,
            'message' => $savedAsRevision
                ? 'Update saved as a draft revision. The live published post is unchanged until a publisher approves it.'
                : ($isSuperAdmin && $post->status === 'published'
                    ? 'Post updated and published.'
                    : 'Post updated.'),
            'data'    => $post,
        ]);
    }

    public function submit(Post $post): JsonResponse
    {
        $post = $this->postService->submitForReview($post, request()->user());
        $isUpdate = $post->isRevisionAwaitingPublisher();

        return response()->json([
            'status'  => true,
            'message' => $isUpdate
                ? 'Update submitted for publisher review. The live post stays unchanged until approved.'
                : 'Post submitted for review.',
            'data'    => $post,
        ]);
    }

    public function reject(RejectPostRequest $request, Post $post): JsonResponse
    {
        $wasLiveUpdate = $post->isRevisionAwaitingPublisher();
        $post = $this->postService->reject($post, $request->validated('review_notes'), $request->user());

        return response()->json([
            'status'  => true,
            'message' => $wasLiveUpdate
                ? 'Update returned to the editor for revision. The live published post is unchanged.'
                : 'Post returned to draft for revision.',
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
        $wasLiveUpdate = $post->isRevisionAwaitingPublisher();
        $post = $this->postService->publishNow($post, request()->user());

        return response()->json([
            'status'  => true,
            'message' => $wasLiveUpdate
                ? 'Update approved. The published post is now updated.'
                : 'Post published.',
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

    public function restore(Post $post): JsonResponse
    {
        $post = $this->postService->restore($post);

        return response()->json([
            'status'  => true,
            'message' => 'Post restored to published.',
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
