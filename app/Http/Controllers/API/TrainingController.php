<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Training;
use App\Services\TrainingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrainingController extends Controller
{
    public function __construct(
        private readonly TrainingService $trainingService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 20);
        $region = $request->get('region');
        $search = $request->get('search');
        $category = $request->get('category');

        $trainings = $this->trainingService->list($perPage, $region, $search, $category, true);

        return response()->json([
            'status' => true,
            'message' => 'Trainings fetched successfully.',
            'data' => $trainings,
        ]);
    }

    public function show(Training $training): JsonResponse
    {
        if (! $training->is_active) {
            return response()->json([
                'status' => false,
                'message' => 'Training not found.',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Training fetched successfully.',
            'data' => $training,
        ]);
    }
}
