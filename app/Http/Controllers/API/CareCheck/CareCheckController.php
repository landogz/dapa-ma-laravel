<?php

namespace App\Http\Controllers\API\CareCheck;

use App\Http\Controllers\Controller;
use App\Http\Requests\CareCheck\StoreCareCheckRequest;
use App\Services\CareCheck\CareCheckService;
use App\Support\Care\CareToolkitType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class CareCheckController extends Controller
{
    public function __construct(
        private readonly CareCheckService $careCheckService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'type' => ['sometimes', 'nullable', 'string', CareToolkitType::validationRule()],
        ]);

        $paginator = $this->careCheckService->listForUser(
            $request->user(),
            (int) ($validated['per_page'] ?? 20),
            $validated['type'] ?? null,
        );

        return response()->json([
            'status' => true,
            'message' => 'Care checks fetched successfully.',
            'data' => $paginator->through(
                fn ($item) => $this->careCheckService->format($item),
            ),
        ]);
    }

    public function latest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', CareToolkitType::validationRule()],
        ]);

        $result = $this->careCheckService->latestForUser(
            $request->user(),
            $validated['type'],
        );

        return response()->json([
            'status' => true,
            'message' => 'Latest care check fetched successfully.',
            'data' => $result ? $this->careCheckService->format($result) : null,
        ]);
    }

    public function store(StoreCareCheckRequest $request): JsonResponse
    {
        try {
            $result = $this->careCheckService->store(
                $request->user(),
                $request->validated('check_type'),
                $request->validated('answers'),
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'status' => false,
                'message' => $exception->getMessage(),
                'errors' => ['answers' => [$exception->getMessage()]],
            ], 422);
        }

        return response()->json([
            'status' => true,
            'message' => 'Care check saved.',
            'data' => $this->careCheckService->format($result),
        ], 201);
    }
}
