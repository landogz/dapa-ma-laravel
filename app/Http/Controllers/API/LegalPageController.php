<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\LegalPageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LegalPageController extends Controller
{
    public function __construct(
        private readonly LegalPageService $legalPageService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $locale = $request->string('locale')->toString() ?: 'en';

        return response()->json([
            'status' => true,
            'message' => 'Legal pages fetched.',
            'data' => $this->legalPageService->publicIndex($locale),
        ]);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $locale = $request->string('locale')->toString() ?: 'en';

        return response()->json([
            'status' => true,
            'message' => 'Legal page fetched.',
            'data' => $this->legalPageService->publicShow($slug, $locale),
        ]);
    }
}
