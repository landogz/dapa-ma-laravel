<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTrainingRequest;
use App\Http\Requests\Admin\UpdateTrainingRequest;
use App\Models\Training;
use App\Services\TrainingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrainingAdminController extends Controller
{
    public function __construct(
        private readonly TrainingService $trainingService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 20);
        $region = $request->string('region')->toString() ?: null;
        $search = $request->string('search')->toString() ?: null;
        $category = $request->string('category')->toString() ?: null;

        return response()->json([
            'status' => true,
            'data' => $this->trainingService->list($perPage, $region, $search, $category, false),
        ]);
    }

    public function show(Training $training): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => $training,
        ]);
    }

    public function store(StoreTrainingRequest $request): JsonResponse
    {
        $training = $this->trainingService->create($request->validated());

        return response()->json([
            'status' => true,
            'message' => 'Training created.',
            'data' => $training,
        ], 201);
    }

    public function update(UpdateTrainingRequest $request, Training $training): JsonResponse
    {
        $training = $this->trainingService->update($training, $request->validated());

        return response()->json([
            'status' => true,
            'message' => 'Training updated.',
            'data' => $training,
        ]);
    }

    public function destroy(Training $training): JsonResponse
    {
        $this->trainingService->delete($training);

        return response()->json([
            'status' => true,
            'message' => 'Training deleted.',
        ]);
    }
}
