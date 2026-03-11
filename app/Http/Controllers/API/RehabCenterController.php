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
        $region  = $request->get('region');
        $search  = $request->get('search');

        $centers = $this->rehabCenterService->list($perPage, $region, $search);

        return response()->json([
            'status'  => true,
            'message' => 'Rehab centers fetched successfully.',
            'data'    => $centers,
        ]);
    }
}

