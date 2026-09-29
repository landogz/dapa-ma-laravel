<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Services\DiaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiaryAdminController extends Controller
{
    public function __construct(
        private readonly DiaryService $diaryService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:500'],
            'user_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'sky' => ['sometimes', 'nullable', 'string', 'max:64'],
            'search' => ['sometimes', 'nullable', 'string', 'max:200'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);
        $filters = [
            'user_id' => $validated['user_id'] ?? null,
            'sky' => $validated['sky'] ?? null,
            'search' => $validated['search'] ?? null,
        ];

        $paginator = $this->diaryService->listAdmin($perPage, $filters);

        return response()->json([
            'status' => true,
            'message' => 'Journal entries fetched successfully.',
            'data' => $paginator->through(
                fn ($entry) => $this->diaryService->formatAdminEntry($entry),
            ),
        ]);
    }

    public function users(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => 'Journal users fetched successfully.',
            'data' => $this->diaryService->listAdminUsers(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $entry = $this->diaryService->showAdmin($id);

        return response()->json([
            'status' => true,
            'message' => 'Journal entry fetched successfully.',
            'data' => $this->diaryService->formatAdminEntry($entry),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->diaryService->deleteAdmin($id);

        return response()->json([
            'status' => true,
            'message' => 'Journal entry deleted successfully.',
            'data' => null,
        ]);
    }
}
