<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreIecMaterialRequest;
use App\Http\Requests\Admin\UpdateIecMaterialRequest;
use App\Models\IecMaterial;
use App\Services\IecMaterialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IecMaterialAdminController extends Controller
{
    public function __construct(
        private readonly IecMaterialService $iecMaterialService,
    ) {
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
            ),
        ]);
    }

    public function show(IecMaterial $iecMaterial): JsonResponse
    {
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
        ]);

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
        ]);

        return response()->json([
            'status' => true,
            'message' => 'IEC material updated.',
            'data' => $material,
        ]);
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
