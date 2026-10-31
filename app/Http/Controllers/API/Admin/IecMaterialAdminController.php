<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\Admin\Concerns\HandlesContentWorkflow;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectContentRequest;
use App\Http\Requests\Admin\StoreIecMaterialRequest;
use App\Http\Requests\Admin\UpdateIecMaterialRequest;
use App\Models\IecMaterial;
use App\Services\ContentWorkflow\ContentWorkflowService;
use App\Services\IecMaterialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IecMaterialAdminController extends Controller
{
    use HandlesContentWorkflow {
        submit as protected workflowSubmit;
        reject as protected workflowReject;
        publish as protected workflowPublish;
    }

    public function __construct(
        private readonly IecMaterialService $iecMaterialService,
        private readonly ContentWorkflowService $contentWorkflow,
    ) {
    }

    protected function contentWorkflowSection(): string
    {
        return 'iec-materials';
    }

    protected function contentWorkflowService(): ContentWorkflowService
    {
        return $this->contentWorkflow;
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => $this->iecMaterialService->list(
                (int) $request->integer('per_page', 100),
                $request->string('search')->toString() ?: null,
                $request->string('topic')->toString() ?: null,
                $request->string('media_type')->toString() ?: null,
                false,
                $request->user(),
            ),
        ]);
    }

    public function show(IecMaterial $iecMaterial): JsonResponse
    {
        $this->assertVisibleToAdmin(request(), $iecMaterial);

        return response()->json([
            'status' => true,
            'data' => $iecMaterial,
        ]);
    }

    public function store(StoreIecMaterialRequest $request): JsonResponse
    {
        $material = $this->iecMaterialService->create([
            ...$request->validated(),
            'media_file' => $request->file('media_file'),
            'thumbnail_file' => $request->file('thumbnail_file'),
        ], $request->user());

        return response()->json([
            'status' => true,
            'message' => 'IEC material created.',
            'data' => $material,
        ], 201);
    }

    public function update(UpdateIecMaterialRequest $request, IecMaterial $iecMaterial): JsonResponse
    {
        $material = $this->iecMaterialService->update($iecMaterial, [
            ...$request->validated(),
            'media_file' => $request->file('media_file'),
            'thumbnail_file' => $request->file('thumbnail_file'),
        ], $request->user());

        return response()->json([
            'status' => true,
            'message' => 'IEC material updated.',
            'data' => $material,
        ]);
    }

    public function submit(Request $request, IecMaterial $iecMaterial): JsonResponse
    {
        return $this->workflowSubmit($request, $iecMaterial);
    }

    public function reject(RejectContentRequest $request, IecMaterial $iecMaterial): JsonResponse
    {
        return $this->workflowReject($request, $iecMaterial);
    }

    public function publish(Request $request, IecMaterial $iecMaterial): JsonResponse
    {
        return $this->workflowPublish($request, $iecMaterial);
    }

    public function destroy(IecMaterial $iecMaterial): JsonResponse
    {
        $this->iecMaterialService->delete($iecMaterial);

        return response()->json([
            'status' => true,
            'message' => 'IEC material deleted.',
        ]);
    }
}
