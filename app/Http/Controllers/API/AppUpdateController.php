<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\AppUpdateService;
use Illuminate\Http\JsonResponse;

class AppUpdateController extends Controller
{
    public function __construct(
        private readonly AppUpdateService $appUpdateService,
    ) {
    }

    public function show(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => 'App update info fetched.',
            'data' => $this->appUpdateService->current(),
        ]);
    }
}
