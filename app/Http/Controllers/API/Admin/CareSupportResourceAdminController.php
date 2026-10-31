<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\Admin\Concerns\HandlesContentWorkflow;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectContentRequest;
use App\Http\Requests\Admin\StoreCareSupportResourceRequest;
use App\Http\Requests\Admin\UpdateCareSupportResourceRequest;
use App\Models\CareSupportResource;
use App\Services\CareSupportResourceService;
use App\Services\ContentWorkflow\ContentWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CareSupportResourceAdminController extends Controller
{
    use HandlesContentWorkflow {
        submit as protected workflowSubmit;
        reject as protected workflowReject;
        publish as protected workflowPublish;
        archive as protected workflowArchive;
        restore as protected workflowRestore;
    }

    public function __construct(
        private readonly CareSupportResourceService $careSupportResourceService,
        private readonly ContentWorkflowService $contentWorkflow,
    ) {
    }

    protected function contentWorkflowSection(): string
    {
        return 'care-support';
    }

    protected function contentWorkflowService(): ContentWorkflowService
    {
        return $this->contentWorkflow;
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->careSupportResourceService->list(
            min(500, max(1, (int) $request->integer('per_page', 200))),
            $request->string('search')->toString() ?: null,
            $request->string('category')->toString() ?: null,
            $request->user(),
        );

        return response()->json([
            'status'  => true,
            'message' => 'Care support resources fetched.',
            'data'    => $paginator->through(
                fn (CareSupportResource $r) => $r->toAdminArray(),
            ),
        ]);
    }

    public function show(CareSupportResource $careSupportResource): JsonResponse
    {
        $this->assertVisibleToAdmin(request(), $careSupportResource);

        return response()->json([
            'status' => true,
            'data'   => $careSupportResource->toAdminArray(),
        ]);
    }

    public function store(StoreCareSupportResourceRequest $request): JsonResponse
    {
        $resource = $this->careSupportResourceService->create($request->validated(), $request->user());

        return response()->json([
            'status'  => true,
            'message' => 'Care support resource created.',
            'data'    => $resource->toAdminArray(),
        ], 201);
    }

    public function update(
        UpdateCareSupportResourceRequest $request,
        CareSupportResource $careSupportResource,
    ): JsonResponse {
        $resource = $this->careSupportResourceService->update(
            $careSupportResource,
            $request->validated(),
            $request->user(),
        );

        return response()->json([
            'status'  => true,
            'message' => 'Care support resource updated.',
            'data'    => $resource->toAdminArray(),
        ]);
    }

    public function submit(Request $request, CareSupportResource $careSupportResource): JsonResponse
    {
        return $this->workflowSubmit($request, $careSupportResource);
    }

    public function reject(RejectContentRequest $request, CareSupportResource $careSupportResource): JsonResponse
    {
        return $this->workflowReject($request, $careSupportResource);
    }

    public function publish(Request $request, CareSupportResource $careSupportResource): JsonResponse
    {
        return $this->workflowPublish($request, $careSupportResource);
    }

    public function archive(Request $request, CareSupportResource $careSupportResource): JsonResponse
    {
        return $this->workflowArchive($request, $careSupportResource);
    }

    public function restore(Request $request, CareSupportResource $careSupportResource): JsonResponse
    {
        return $this->workflowRestore($request, $careSupportResource);
    }

    public function destroy(CareSupportResource $careSupportResource): JsonResponse
    {
        $this->careSupportResourceService->delete($careSupportResource);

        return response()->json([
            'status'  => true,
            'message' => 'Care support resource deleted.',
        ]);
    }
}
