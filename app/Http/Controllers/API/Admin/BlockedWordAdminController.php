<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBlockedWordRequest;
use App\Http\Requests\Admin\UpdateBlockedWordRequest;
use App\Models\BlockedWord;
use App\Services\BlockedWord\BlockedWordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlockedWordAdminController extends Controller
{
    public function __construct(
        private readonly BlockedWordService $blockedWordService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:500'],
            'search' => ['sometimes', 'nullable', 'string', 'max:80'],
            'is_active' => ['sometimes', 'nullable', 'boolean'],
        ]);

        $filters = [
            'search' => $validated['search'] ?? null,
            'is_active' => array_key_exists('is_active', $validated) ? $validated['is_active'] : null,
        ];

        $paginator = $this->blockedWordService->listAdmin(
            (int) ($validated['per_page'] ?? 200),
            $filters,
        );

        return response()->json([
            'status' => true,
            'message' => 'Blocked words fetched successfully.',
            'data' => $paginator->through(fn (BlockedWord $word) => $word->toAdminArray()),
            'meta' => [
                'status_counts' => $this->blockedWordService->statusCounts(),
            ],
        ]);
    }

    public function show(BlockedWord $blockedWord): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => 'Blocked word fetched successfully.',
            'data' => $blockedWord->toAdminArray(),
        ]);
    }

    public function store(StoreBlockedWordRequest $request): JsonResponse
    {
        $word = $this->blockedWordService->create($request->validated());

        return response()->json([
            'status' => true,
            'message' => 'Blocked word added.',
            'data' => $word->toAdminArray(),
        ], 201);
    }

    public function update(UpdateBlockedWordRequest $request, BlockedWord $blockedWord): JsonResponse
    {
        $word = $this->blockedWordService->update($blockedWord, $request->validated());

        return response()->json([
            'status' => true,
            'message' => 'Blocked word updated.',
            'data' => $word->toAdminArray(),
        ]);
    }

    public function destroy(BlockedWord $blockedWord): JsonResponse
    {
        $this->blockedWordService->delete($blockedWord);

        return response()->json([
            'status' => true,
            'message' => 'Blocked word deleted.',
        ]);
    }
}
