<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\ProfileStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileStatsController extends Controller
{
    public function __construct(
        private readonly ProfileStatsService $profileStatsService,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        $summary = $this->profileStatsService->summary($request->user());

        return response()->json([
            'status' => true,
            'message' => 'Profile summary fetched successfully.',
            'data' => $summary,
        ]);
    }
}
