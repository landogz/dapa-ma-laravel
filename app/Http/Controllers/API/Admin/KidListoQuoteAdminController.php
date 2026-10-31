<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\Admin\Concerns\HandlesContentWorkflow;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectContentRequest;
use App\Http\Requests\Admin\StoreKidListoQuoteRequest;
use App\Http\Requests\Admin\UpdateKidListoQuoteRequest;
use App\Models\KidListoQuote;
use App\Services\ContentWorkflow\ContentWorkflowService;
use App\Services\KidListoQuoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KidListoQuoteAdminController extends Controller
{
    use HandlesContentWorkflow {
        submit as protected workflowSubmit;
        reject as protected workflowReject;
        publish as protected workflowPublish;
        archive as protected workflowArchive;
        restore as protected workflowRestore;
    }

    public function __construct(
        private readonly KidListoQuoteService $kidListoQuoteService,
        private readonly ContentWorkflowService $contentWorkflow,
    ) {
    }

    protected function contentWorkflowSection(): string
    {
        return 'kid-listo';
    }

    protected function contentWorkflowService(): ContentWorkflowService
    {
        return $this->contentWorkflow;
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => 'Kid Listo quotes fetched.',
            'data' => $this->kidListoQuoteService->list(
                (int) $request->integer('per_page', 200),
                $request->string('search')->toString() ?: null,
                $request->user(),
            ),
        ]);
    }

    public function show(KidListoQuote $kidListoQuote): JsonResponse
    {
        $this->assertVisibleToAdmin(request(), $kidListoQuote);

        return response()->json([
            'status' => true,
            'data' => $kidListoQuote,
        ]);
    }

    public function store(StoreKidListoQuoteRequest $request): JsonResponse
    {
        $quote = $this->kidListoQuoteService->create($request->validated(), $request->user());

        return response()->json([
            'status' => true,
            'message' => 'Kid Listo quote created.',
            'data' => $quote,
        ], 201);
    }

    public function update(UpdateKidListoQuoteRequest $request, KidListoQuote $kidListoQuote): JsonResponse
    {
        $wasPublished = $kidListoQuote->isWorkflowPublished();
        $quote = $this->kidListoQuoteService->update($kidListoQuote, $request->validated(), $request->user());
        $savedAsRevision = $request->user()?->role === 'editor'
            && $wasPublished
            && $quote->isRevisionDraft();

        return response()->json([
            'status' => true,
            'message' => $savedAsRevision
                ? 'Update saved as a draft revision. The live published quote is unchanged until a publisher approves it.'
                : 'Kid Listo quote updated.',
            'data' => $quote,
        ]);
    }

    public function submit(Request $request, KidListoQuote $kidListoQuote): JsonResponse
    {
        return $this->workflowSubmit($request, $kidListoQuote);
    }

    public function reject(RejectContentRequest $request, KidListoQuote $kidListoQuote): JsonResponse
    {
        return $this->workflowReject($request, $kidListoQuote);
    }

    public function publish(Request $request, KidListoQuote $kidListoQuote): JsonResponse
    {
        return $this->workflowPublish($request, $kidListoQuote);
    }

    public function archive(Request $request, KidListoQuote $kidListoQuote): JsonResponse
    {
        return $this->workflowArchive($request, $kidListoQuote);
    }

    public function restore(Request $request, KidListoQuote $kidListoQuote): JsonResponse
    {
        return $this->workflowRestore($request, $kidListoQuote);
    }

    public function destroy(KidListoQuote $kidListoQuote): JsonResponse
    {
        $this->kidListoQuoteService->delete($kidListoQuote);

        return response()->json([
            'status' => true,
            'message' => 'Kid Listo quote deleted.',
        ]);
    }
}
