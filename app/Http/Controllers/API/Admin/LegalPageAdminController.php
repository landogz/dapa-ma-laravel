<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\Admin\Concerns\HandlesContentWorkflow;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectContentRequest;
use App\Http\Requests\Admin\UpdateLegalPageRequest;
use App\Services\ContentWorkflow\ContentWorkflowService;
use App\Services\LegalPageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LegalPageAdminController extends Controller
{
    use HandlesContentWorkflow {
        submit as protected workflowSubmit;
        reject as protected workflowReject;
        publish as protected workflowPublish;
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
            'data' => $this->legalPageService->listForAdmin(),
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => 'Legal page fetched.',
            'data' => $this->legalPageService->findForAdmin($slug),
        ]);
    }

    public function update(UpdateLegalPageRequest $request, string $slug): JsonResponse
    {
        $page = $this->legalPageService->update($slug, $request->validated(), $request->user());

        return response()->json([
            'status' => true,
            'message' => 'Legal page updated.',
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
}
