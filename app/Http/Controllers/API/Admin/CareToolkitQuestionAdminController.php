<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCareToolkitQuestionRequest;
use App\Http\Requests\Admin\UpdateCareToolkitQuestionRequest;
use App\Models\CareToolkitQuestion;
use App\Services\CareToolkitQuestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CareToolkitQuestionAdminController extends Controller
{
    public function __construct(
        private readonly CareToolkitQuestionService $careToolkitQuestionService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->careToolkitQuestionService->list(
            min(500, max(1, (int) $request->integer('per_page', 200))),
            $request->string('search')->toString() ?: null,
            $request->string('toolkit_type')->toString() ?: null,
        );

        return response()->json([
            'status'  => true,
            'message' => 'Care toolkit questions fetched.',
            'data'    => $paginator->through(
                fn (CareToolkitQuestion $q) => $q->toAdminArray(),
            ),
        ]);
    }

    public function show(CareToolkitQuestion $careToolkitQuestion): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data'   => $careToolkitQuestion->toAdminArray(),
        ]);
    }

    public function store(StoreCareToolkitQuestionRequest $request): JsonResponse
    {
        $question = $this->careToolkitQuestionService->create($request->validated());

        return response()->json([
            'status'  => true,
            'message' => 'Care toolkit question created.',
            'data'    => $question->toAdminArray(),
        ], 201);
    }

    public function update(
        UpdateCareToolkitQuestionRequest $request,
        CareToolkitQuestion $careToolkitQuestion,
    ): JsonResponse {
        $question = $this->careToolkitQuestionService->update(
            $careToolkitQuestion,
            $request->validated(),
        );

        return response()->json([
            'status'  => true,
            'message' => 'Care toolkit question updated.',
            'data'    => $question->toAdminArray(),
        ]);
    }

    public function destroy(CareToolkitQuestion $careToolkitQuestion): JsonResponse
    {
        $this->careToolkitQuestionService->delete($careToolkitQuestion);

        return response()->json([
            'status'  => true,
            'message' => 'Care toolkit question deleted.',
        ]);
    }
}
