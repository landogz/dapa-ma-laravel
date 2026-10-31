<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\Admin\Concerns\HandlesContentWorkflow;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexBlockedWordRequest;
use App\Http\Requests\Admin\RejectContentRequest;
use App\Http\Requests\Admin\StoreBlockedWordRequest;
use App\Http\Requests\Admin\UpdateBlockedWordRequest;
use App\Models\BlockedWord;
use App\Services\BlockedWord\BlockedWordService;
use App\Services\ContentWorkflow\ContentWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlockedWordAdminController extends Controller
{
    use HandlesContentWorkflow {
        submit as protected workflowSubmit;
        reject as protected workflowReject;
        publish as protected workflowPublish;
        archive as protected workflowArchive;
        restore as protected workflowRestore;
    }

    public function __construct(
        private readonly BlockedWordService $blockedWordService,
        private readonly ContentWorkflowService $contentWorkflow,
    ) {
    }

    protected function contentWorkflowSection(): string
    {
        return 'word-blocking';
    }

    protected function contentWorkflowService(): ContentWorkflowService
    {
        return $this->contentWorkflow;
    }

    public function index(IndexBlockedWordRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $filters = [
            'search' => $validated['search'] ?? null,
            'is_active' => array_key_exists('is_active', $validated) ? $validated['is_active'] : null,
        ];

        $paginator = $this->blockedWordService->listAdmin(
            (int) ($validated['per_page'] ?? 200),
            $filters,
            $request->user(),
        );

        return response()->json([
            'status' => true,
            'message' => 'Blocked words fetched successfully.',
            'data' => $paginator->through(fn (BlockedWord $word) => $word->toAdminArray()),
            'meta' => [
                'status_counts' => $this->blockedWordService->statusCounts(),
            ],
        ]);
    }

    public function show(BlockedWord $blockedWord): JsonResponse
    {
        $this->assertVisibleToAdmin(request(), $blockedWord);

        return response()->json([
            'status' => true,
            'message' => 'Blocked word fetched successfully.',
            'data' => $blockedWord->toAdminArray(),
        ]);
    }

    public function store(StoreBlockedWordRequest $request): JsonResponse
    {
        $word = $this->blockedWordService->create($request->validated(), $request->user());

        return response()->json([
            'status' => true,
            'message' => 'Blocked word added.',
            'data' => $word->toAdminArray(),
        ], 201);
    }

    public function update(UpdateBlockedWordRequest $request, BlockedWord $blockedWord): JsonResponse
    {
        $word = $this->blockedWordService->update($blockedWord, $request->validated(), $request->user());

        return response()->json([
            'status' => true,
            'message' => 'Blocked word updated.',
            'data' => $word->toAdminArray(),
        ]);
    }

    public function submit(Request $request, BlockedWord $blockedWord): JsonResponse
    {
        return $this->workflowSubmit($request, $blockedWord);
    }

    public function reject(RejectContentRequest $request, BlockedWord $blockedWord): JsonResponse
    {
        return $this->workflowReject($request, $blockedWord);
    }

    public function publish(Request $request, BlockedWord $blockedWord): JsonResponse
    {
        return $this->workflowPublish($request, $blockedWord);
    }

    public function archive(Request $request, BlockedWord $blockedWord): JsonResponse
    {
        return $this->workflowArchive($request, $blockedWord);
    }

    public function restore(Request $request, BlockedWord $blockedWord): JsonResponse
    {
        return $this->workflowRestore($request, $blockedWord);
    }

    public function destroy(BlockedWord $blockedWord): JsonResponse
    {
        $this->blockedWordService->delete($blockedWord);

        return response()->json([
            'status' => true,
            'message' => 'Blocked word deleted.',
        ]);
    }
}
