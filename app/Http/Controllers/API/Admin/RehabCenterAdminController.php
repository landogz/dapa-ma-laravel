<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRehabCenterRequest;
use App\Http\Requests\Admin\UpdateRehabCenterRequest;
use App\Models\RehabCenter;
use App\Services\RehabCenterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RehabCenterAdminController extends Controller
{
    public function __construct(
        private readonly RehabCenterService $rehabCenterService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 20);
        $region  = $request->string('region')->toString() ?: null;
        $search  = $request->string('search')->toString() ?: null;

        return response()->json([
            'status' => true,
            'data'   => $this->rehabCenterService->list($perPage, $region, $search),
        ]);
    }

    public function show(RehabCenter $rehabCenter): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data'   => $rehabCenter,
        ]);
    }

    public function store(StoreRehabCenterRequest $request): JsonResponse
    {
        $center = $this->rehabCenterService->create($request->validated());

        return response()->json([
            'status'  => true,
            'message' => 'Rehab center created.',
            'data'    => $center,
        ], 201);
    }

    public function update(UpdateRehabCenterRequest $request, RehabCenter $rehabCenter): JsonResponse
    {
        $center = $this->rehabCenterService->update($rehabCenter, $request->validated());

        return response()->json([
            'status'  => true,
            'message' => 'Rehab center updated.',
            'data'    => $center,
        ]);
    }

    public function destroy(RehabCenter $rehabCenter): JsonResponse
    {
        $this->rehabCenterService->delete($rehabCenter);

        return response()->json([
            'status'  => true,
            'message' => 'Rehab center deleted.',
        ]);
    }
}
