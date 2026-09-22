<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCareSupportResourceRequest;
use App\Http\Requests\Admin\UpdateCareSupportResourceRequest;
use App\Models\CareSupportResource;
use App\Services\CareSupportResourceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CareSupportResourceAdminController extends Controller
{
    public function __construct(
        private readonly CareSupportResourceService $careSupportResourceService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->careSupportResourceService->list(
            min(500, max(1, (int) $request->integer('per_page', 200))),
            $request->string('search')->toString() ?: null,
            $request->string('category')->toString() ?: null,
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
        return response()->json([
            'status' => true,
            'data'   => $careSupportResource->toAdminArray(),
        ]);
    }

    public function store(StoreCareSupportResourceRequest $request): JsonResponse
    {
        $resource = $this->careSupportResourceService->create($request->validated());

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
        );

        return response()->json([
            'status'  => true,
            'message' => 'Care support resource updated.',
            'data'    => $resource->toAdminArray(),
        ]);
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
