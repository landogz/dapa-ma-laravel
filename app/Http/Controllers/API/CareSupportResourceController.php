<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CareSupportResource;
use App\Services\CareSupportResourceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CareSupportResourceController extends Controller
{
    public function __construct(
        private readonly CareSupportResourceService $careSupportResourceService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => ['required', 'string', Rule::in(CareSupportResource::CATEGORIES)],
            'locale'   => ['nullable', 'string', 'max:8'],
        ]);

        $items = $this->careSupportResourceService->publicByCategory(
            $validated['category'],
            $validated['locale'] ?? $request->header('X-Locale', 'en'),
        );

        return response()->json([
            'status'  => true,
            'message' => 'Care support resources fetched.',
            'data'    => [
                'category'  => $validated['category'],
                'resources' => $items,
            ],
        ]);
    }
}
