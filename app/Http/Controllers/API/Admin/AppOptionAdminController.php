<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\Admin\Concerns\HandlesContentWorkflow;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectContentRequest;
use App\Http\Requests\Admin\StoreAppOptionRequest;
use App\Http\Requests\Admin\UpdateAppOptionRequest;
use App\Models\AppOption;
use App\Services\AppOptionService;
use App\Services\ContentWorkflow\ContentWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppOptionAdminController extends Controller
{
    use HandlesContentWorkflow {
        submit as protected workflowSubmit;
        reject as protected workflowReject;
        publish as protected workflowPublish;
        archive as protected workflowArchive;
        restore as protected workflowRestore;
    }

    public function __construct(
        private readonly AppOptionService $appOptionService,
        private readonly ContentWorkflowService $contentWorkflow,
    ) {
    }

    protected function contentWorkflowSection(): string
    {
        return 'lookups';
    }

    protected function contentWorkflowService(): ContentWorkflowService
    {
        return $this->contentWorkflow;
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->appOptionService->list(
            min(500, max(1, (int) $request->integer('per_page', 200))),
            $request->string('search')->toString() ?: null,
            $request->string('group')->toString() ?: null,
            $request->user(),
        );

        return response()->json([
            'status' => true,
            'message' => 'App options fetched.',
            'data' => $paginator->through(fn (AppOption $option) => $option->toAdminArray()),
            'meta' => $this->appOptionService->adminMeta($request->user()),
        ]);
    }

    public function show(AppOption $appOption): JsonResponse
    {
        $this->assertVisibleToAdmin(request(), $appOption);

        return response()->json([
            'status' => true,
            'data' => $appOption->toAdminArray(),
        ]);
    }

    public function store(StoreAppOptionRequest $request): JsonResponse
    {
        $option = $this->appOptionService->create($request->validated(), $request->user());
        $isSuperAdmin = $request->user()?->role === 'super_admin';

        return response()->json([
            'status' => true,
            'message' => $isSuperAdmin
                ? 'Option published.'
                : 'Option created as a draft.',
            'data' => $option->toAdminArray(),
        ], 201);
    }

    public function update(UpdateAppOptionRequest $request, AppOption $appOption): JsonResponse
    {
        $wasPublished = $appOption->isWorkflowPublished();
        $option = $this->appOptionService->update($appOption, $request->validated(), $request->user());
        $savedAsRevision = $request->user()?->role === 'editor'
            && $wasPublished
            && $option->isRevisionDraft();

        return response()->json([
            'status' => true,
            'message' => $savedAsRevision
                ? 'Update saved as a draft revision. The live published option is unchanged until a publisher approves it.'
                : 'Option updated.',
            'data' => $option->toAdminArray(),
        ]);
    }

    public function submit(Request $request, AppOption $appOption): JsonResponse
    {
        return $this->workflowSubmit($request, $appOption);
    }

    public function reject(RejectContentRequest $request, AppOption $appOption): JsonResponse
    {
        return $this->workflowReject($request, $appOption);
    }

    public function publish(Request $request, AppOption $appOption): JsonResponse
    {
        return $this->workflowPublish($request, $appOption);
    }

    public function archive(Request $request, AppOption $appOption): JsonResponse
    {
        return $this->workflowArchive($request, $appOption);
    }

    public function restore(Request $request, AppOption $appOption): JsonResponse
    {
        return $this->workflowRestore($request, $appOption);
    }

    public function destroy(AppOption $appOption): JsonResponse
    {
        $this->appOptionService->delete($appOption);

        return response()->json([
            'status' => true,
            'message' => 'Option deleted.',
        ]);
    }
}
