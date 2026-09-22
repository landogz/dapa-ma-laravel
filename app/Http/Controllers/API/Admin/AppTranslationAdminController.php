<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAppTranslationRequest;
use App\Http\Requests\Admin\UpdateAppTranslationRequest;
use App\Models\AppTranslation;
use App\Services\AppTranslationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppTranslationAdminController extends Controller
{
    public function __construct(
        private readonly AppTranslationService $appTranslationService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $active = $request->has('is_active')
            ? filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            : null;

        return response()->json([
            'status' => true,
            'data' => $this->appTranslationService->list(
                (int) $request->integer('per_page', 200),
                $request->string('search')->toString() ?: null,
                $request->string('group')->toString() ?: null,
                $active,
            ),
            'meta' => [
                'groups' => $this->appTranslationService->groups()->values(),
            ],
        ]);
    }

    public function show(AppTranslation $appTranslation): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => $appTranslation,
        ]);
    }

    public function store(StoreAppTranslationRequest $request): JsonResponse
    {
        $translation = $this->appTranslationService->create($request->validated());

        return response()->json([
            'status' => true,
            'message' => 'Translation created.',
            'data' => $translation,
        ], 201);
    }

    public function update(UpdateAppTranslationRequest $request, AppTranslation $appTranslation): JsonResponse
    {
        $translation = $this->appTranslationService->update($appTranslation, $request->validated());

        return response()->json([
            'status' => true,
            'message' => 'Translation updated.',
            'data' => $translation,
        ]);
    }

    public function destroy(AppTranslation $appTranslation): JsonResponse
    {
        $this->appTranslationService->delete($appTranslation);

        return response()->json([
            'status' => true,
            'message' => 'Translation deleted.',
        ]);
    }
}
