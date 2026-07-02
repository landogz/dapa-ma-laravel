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
        $perPage = (int) $request->integer('per_page', 20);
        $paginator = $this->diaryService->listAdmin($perPage);

        return response()->json([
            'status'  => true,
            'message' => 'Diary entries fetched successfully.',
            'data'    => $paginator->through(
                fn ($entry) => $this->diaryService->formatAdminEntry($entry),
            ),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $entry = $this->diaryService->showAdmin($id);

        return response()->json([
            'status'  => true,
            'message' => 'Diary entry fetched successfully.',
            'data'    => $this->diaryService->formatAdminEntry($entry),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->diaryService->deleteAdmin($id);

        return response()->json([
            'status'  => true,
            'message' => 'Diary entry deleted successfully.',
            'data'    => null,
        ]);
    }
}
