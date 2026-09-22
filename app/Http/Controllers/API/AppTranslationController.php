<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\AppTranslationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppTranslationController extends Controller
{
    public function __construct(
        private readonly AppTranslationService $appTranslationService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $locale = $request->string('locale')->toString() ?: 'en';

        return response()->json([
            'status' => true,
            'message' => 'Translations fetched.',
            'data' => $this->appTranslationService->publicBundle($locale),
        ]);
    }

    public function show(string $locale): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => 'Translations fetched.',
            'data' => $this->appTranslationService->publicBundle($locale),
        ]);
    }
}
