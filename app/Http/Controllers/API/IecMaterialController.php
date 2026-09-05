<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\IecMaterial;
use App\Services\IecMaterialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IecMaterialController extends Controller
{
    public function __construct(
        private readonly IecMaterialService $iecMaterialService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $materials = $this->iecMaterialService->list(
            (int) $request->integer('per_page', 24),
            $request->get('search'),
            $request->get('topic'),
            $request->get('media_type'),
            true,
        );

        return response()->json([
            'status' => true,
            'message' => 'IEC materials fetched successfully.',
            'data' => $materials,
        ]);
    }

    public function show(IecMaterial $iecMaterial): JsonResponse
    {
        if (! $iecMaterial->is_active) {
            return response()->json([
                'status' => false,
                'message' => 'IEC material not found.',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'IEC material fetched successfully.',
            'data' => $iecMaterial,
        ]);
    }
}
