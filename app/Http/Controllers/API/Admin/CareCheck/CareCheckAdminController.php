<?php

namespace App\Http\Controllers\API\Admin\CareCheck;

use App\Http\Controllers\Controller;
use App\Models\CareCheckResult;
use App\Services\CareCheck\CareCheckService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CareCheckAdminController extends Controller
{
    public function __construct(
        private readonly CareCheckService $careCheckService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:500'],
            'check_type' => ['sometimes', 'nullable', 'string', Rule::in(CareCheckResult::TYPES)],
            'display_tier' => ['sometimes', 'nullable', 'string', Rule::in(['low', 'high'])],
            'search' => ['sometimes', 'nullable', 'string', 'max:200'],
        ]);

        $paginator = $this->careCheckService->listAdmin(
            (int) ($validated['per_page'] ?? 20),
            [
                'check_type' => $validated['check_type'] ?? null,
                'display_tier' => $validated['display_tier'] ?? null,
                'search' => $validated['search'] ?? null,
            ],
        );

        return response()->json([
            'status' => true,
            'message' => 'Care checks fetched successfully.',
            'data' => $paginator->through(
                fn ($item) => $this->careCheckService->formatAdmin($item),
            ),
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $days = (int) $request->query('days', 30);
        if (! in_array($days, [7, 30, 90], true)) {
            $days = 30;
        }

        return response()->json([
            'status' => true,
            'message' => 'Care check analytics fetched successfully.',
            'data' => $this->careCheckService->summary($days),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $result = $this->careCheckService->showAdmin($id);

        return response()->json([
            'status' => true,
            'message' => 'Care check fetched successfully.',
            'data' => $this->careCheckService->formatAdminDetail($result),
        ]);
    }
}
