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
        $submittedUpdate = ! $isSuperAdmin
            && $wasPublishedLive
            && $post->status === 'published'
            && $post->revision_status === 'pending_review';

        return response()->json([
            'status'  => true,
            'message' => $submittedUpdate
                ? 'Update submitted for publisher review. Publishers were notified. The live post stays unchanged until approved.'
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
                ? 'Update returned to the editor for revision. It leaves the publisher queue until they resubmit. The live published post is unchanged.'
                : 'Post returned to the editor for revision. It leaves the publisher queue until they resubmit.',
            'data'    => $post,
        ]);
    }

    public function schedule(SchedulePostRequest $request, Post $post): JsonResponse
    {
        $wasScheduled = $post->status === 'scheduled';
        $post = $this->postService->schedule($post, $request->validated('publish_date'), $request->user());

        return response()->json([
            'status'  => true,
            'message' => $wasScheduled
                ? 'Schedule updated.'
                : 'Post scheduled for publishing.',
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

    public function archive(Request $request, Post $post): JsonResponse
    {
        $post = $this->postService->archive($post, $request->user());

        return response()->json([
            'status'  => true,
            'message' => 'Post archived.',
            'data'    => $post,
        ]);
    }

    public function restore(Request $request, Post $post): JsonResponse
    {
        $post = $this->postService->restore($post, $request->user());
        $awaitingApproval = $post->status === 'pending_review';

        return response()->json([
            'status'  => true,
            'message' => $awaitingApproval
                ? 'Restore submitted for publisher review. Publishers were notified. The post stays hidden until approved.'
                : 'Post restored to published.',
            'data'    => $post,
        ]);
    }

    public function destroy(Request $request, Post $post): JsonResponse
    {
        $result = $this->postService->deleteDraft($post, $request->user());

        if (($result['mode'] ?? null) === 'discarded') {
            return response()->json([
                'status' => true,
                'message' => 'Draft update discarded. The live published post is unchanged.',
                'data' => $result['post'],
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Draft deleted.',
            'data' => null,
        ]);
    }
}
