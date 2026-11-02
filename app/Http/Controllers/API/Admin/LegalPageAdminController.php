<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\Admin\Concerns\HandlesContentWorkflow;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectContentRequest;
use App\Http\Requests\Admin\UpdateLegalPageRequest;
use App\Services\ContentWorkflow\ContentWorkflowService;
use App\Services\LegalPageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class LegalPageAdminController extends Controller
{
    use HandlesContentWorkflow {
        submit as protected workflowSubmit;
        reject as protected workflowReject;
        publish as protected workflowPublish;
        destroy as protected workflowDestroy;
    }

    public function __construct(
        private readonly LegalPageService $legalPageService,
        private readonly ContentWorkflowService $contentWorkflow,
    ) {
    }

    protected function contentWorkflowSection(): string
    {
        return 'legal-pages';
    }

    protected function contentWorkflowService(): ContentWorkflowService
    {
        return $this->contentWorkflow;
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => 'Legal pages fetched.',
            'data' => $this->legalPageService->listForAdmin(request()->user()),
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $page = $this->legalPageService->findForAdmin($slug);
        $this->assertVisibleToAdmin(request(), $page);

        return response()->json([
            'status' => true,
            'message' => 'Legal page fetched.',
            'data' => $page,
        ]);
    }

    public function update(UpdateLegalPageRequest $request, string $slug): JsonResponse
    {
        $before = $this->legalPageService->findForAdmin($slug);
        $wasPublished = $before->isWorkflowPublished();
        $page = $this->legalPageService->update($slug, $request->validated(), $request->user());
        $submittedUpdate = $request->user()?->role === 'editor'
            && $wasPublished
            && $page->isRevisionAwaitingPublisher();

        return response()->json([
            'status' => true,
            'message' => $submittedUpdate
                ? 'Update submitted for publisher review. Publishers were notified. The live page stays unchanged until approved.'
                : 'Legal page updated.',
            'data' => $page,
        ]);
    }

    public function submit(Request $request, string $slug): JsonResponse
    {
        return $this->workflowSubmit($request, $this->legalPageService->findForAdmin($slug));
    }

    public function reject(RejectContentRequest $request, string $slug): JsonResponse
    {
        return $this->workflowReject($request, $this->legalPageService->findForAdmin($slug));
    }

    public function publish(Request $request, string $slug): JsonResponse
    {
        return $this->workflowPublish($request, $this->legalPageService->findForAdmin($slug));
    }

    public function destroy(Request $request, string $slug): JsonResponse
    {
        return $this->workflowDestroy($request, $this->legalPageService->findForAdmin($slug));
    }

    protected function permanentlyDeleteDraft(Model $model): void
    {
        throw new InvalidArgumentException('Legal pages cannot be deleted. Discard a draft update instead.');
    }
}
