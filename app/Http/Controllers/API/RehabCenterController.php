<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\RehabCenterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RehabCenterController extends Controller
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
        $category = $request->string('category')->toString() ?: null;

        $centers = $this->rehabCenterService->list($perPage, $region, $search, true, $category);

        return response()->json([
            'status'  => true,
            'message' => 'Rehab centers fetched successfully.',
            'data'    => $centers,
        ]);
    }
}

