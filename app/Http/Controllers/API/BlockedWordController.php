<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\BlockedWord\BlockedWordService;
use Illuminate\Http\JsonResponse;

class BlockedWordController extends Controller
{
    public function __construct(
        private readonly BlockedWordService $blockedWordService,
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => 'Blocked words fetched successfully.',
            'data' => $this->blockedWordService->publicCatalog(),
        ]);
    }
}
