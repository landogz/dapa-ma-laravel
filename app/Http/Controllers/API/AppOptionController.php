<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\AppOptionService;
use App\Support\AppOptionGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AppOptionController extends Controller
{
    public function __construct(
        private readonly AppOptionService $appOptionService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'group' => ['nullable', 'string', Rule::in(AppOptionGroup::keys())],
            'locale' => ['nullable', 'string', 'max:8'],
        ]);

        $locale = $validated['locale'] ?? $request->header('X-Locale', 'en');

        return response()->json([
            'status' => true,
            'message' => 'App options fetched.',
            'data' => $this->appOptionService->publicCatalog(
                is_string($locale) && $locale !== '' ? $locale : 'en',
                $validated['group'] ?? null,
            ),
        ]);
    }
}
